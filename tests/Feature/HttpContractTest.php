<?php

declare(strict_types=1);

namespace Tests\Feature;

use Core\MigrationManager;
use PDO;
use PHPUnit\Framework\TestCase;
use function array_keys;
use function dirname;
use function fclose;
use function fopen;
use function getenv;
use function glob;
use function is_array;
use function is_file;
use function json_decode;
use function json_encode;
use function ltrim;
use function microtime;
use function mkdir;
use function preg_match;
use function proc_close;
use function proc_get_status;
use function proc_open;
use function proc_terminate;
use function rmdir;
use function sprintf;
use function stream_context_create;
use function stream_get_contents;
use function stream_get_meta_data;
use function stream_socket_client;
use function stream_socket_get_name;
use function stream_socket_server;
use function stream_set_blocking;
use function strrpos;
use function substr;
use function sys_get_temp_dir;
use function tempnam;
use function unlink;
use function usleep;

final class HttpContractTest extends TestCase
{
    private string $projectRoot;

    private string $databasePath = '';

    private string $sessionPath = '';

    private int $serverPort;

    /** @var resource|null */
    private mixed $serverProcess = null;

    /** @var array<int, resource> */
    private array $serverPipes = [];

    private PDO $database;

    private string $cookie = '';

    private string $csrfToken = '';

    protected function setUp(): void
    {
        parent::setUp();
        $this->projectRoot = dirname(__DIR__, 2);
        $databasePath = tempnam(sys_get_temp_dir(), 'phpenalva-test-');

        if ($databasePath === false) {
            throw new \RuntimeException('Unable to create the temporary HTTP test database.');
        }

        $this->databasePath = $databasePath;
        $this->sessionPath = sprintf('%s-sessions', $databasePath);
        mkdir($this->sessionPath, 0700);
        $this->database = new PDO(sprintf('sqlite:%s', $this->databasePath));
        $this->database->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        (new MigrationManager($this->database))->migrate();
        $this->startServer();
        $payload = $this->decodeResponse($this->request('GET', '/csrf-token'));
        $this->csrfToken = $payload['data']['token'];
    }

    protected function tearDown(): void
    {
        if ($this->serverProcess !== null) {
            proc_terminate($this->serverProcess);

            foreach ($this->serverPipes as $pipe) {
                fclose($pipe);
            }

            proc_close($this->serverProcess);
        }

        $databaseExists = is_file($this->databasePath);

        if ($databaseExists) {
            unlink($this->databasePath);
        }

        foreach (glob(sprintf('%s/*', $this->sessionPath)) ?: [] as $sessionFile) {
            unlink($sessionFile);
        }

        rmdir($this->sessionPath);

        parent::tearDown();
    }

    public function testApiResponsesUseTheEnvelope(): void
    {
        $response = $this->request('GET', '/api');

        self::assertSame(200, $response['status']);
        self::assertStringStartsWith('{', ltrim($response['body']));
        $payload = $this->decodeResponse($response);

        self::assertEqualsCanonicalizing(['status', 'message', 'data', 'errors'], array_keys($payload));
        self::assertSame('success', $payload['status']);
        self::assertIsArray($payload['data']);
    }

    public function testApiResponsesDoNotExposeDatabaseSettings(): void
    {
        $payload = $this->decodeResponse($this->request('GET', '/api'));

        self::assertIsArray($payload['data'] ?? null);
        self::assertArrayNotHasKey('database', $payload['data']);
        self::assertArrayNotHasKey('database_host', $payload['data']);
    }

    public function testUnauthenticatedMutationReturns401Json(): void
    {
        $response = $this->request('POST', '/api/posts', ['description' => 'unauthorized post']);

        self::assertSame(401, $response['status']);
        $payload = $this->decodeResponse($response);
        self::assertSame('error', $payload['status']);
        self::assertSame('Unauthorized', $payload['message']);
        self::assertNull($payload['data']);
        self::assertSame([], $payload['errors']);
    }

    public function testRegistrationRejectsAnEmptyPassword(): void
    {
        $response = $this->request('POST', '/user/create', [
            'name' => 'Invalid Password',
            'email' => 'invalid-password@example.test',
            'password' => '',
        ]);

        self::assertSame(422, $response['status']);
        $payload = $this->decodeResponse($response);
        self::assertSame('error', $payload['status']);
        self::assertIsArray($payload['errors']);
        self::assertNotEmpty($payload['errors']);
        self::assertArrayHasKey('password', $payload['errors']);
    }

    public function testRegistrationReadsJsonFromThePostBody(): void
    {
        $email = 'json-registration@example.test';
        $response = $this->request('POST', '/user/create', [
            'name' => 'JSON Registration',
            'email' => $email,
            'password' => 'valid-password',
        ]);

        self::assertSame(201, $response['status']);
        self::assertSame('success', $this->decodeResponse($response)['status']);
        $statement = $this->database->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $statement->execute(['email' => $email]);
        self::assertSame(1, (int) $statement->fetchColumn());
    }

    public function testPersistenceFailureCannotReturnSuccess(): void
    {
        $this->database->exec("CREATE TRIGGER reject_user_insert BEFORE INSERT ON users BEGIN SELECT RAISE(ABORT, 'forced persistence failure'); END");
        $response = $this->request('POST', '/user/create', [
            'name' => 'Failed Persistence',
            'email' => 'failed-persistence@example.test',
            'password' => 'valid-password',
        ]);

        self::assertSame(500, $response['status']);
        self::assertSame('error', $this->decodeResponse($response)['status']);
    }

    public function testUnknownApiRouteReturns404Json(): void
    {
        $response = $this->request('GET', '/api/route-that-does-not-exist');

        self::assertSame(404, $response['status']);
        self::assertSame('error', $this->decodeResponse($response)['status']);
    }

    public function testDebugRouteIsGatedByAppDebug(): void
    {
        self::assertSame(404, $this->request('GET', '/debug')['status']);
        $this->stopServer();
        $this->startServer('true');
        $response = $this->request('GET', '/debug');
        self::assertSame(200, $response['status']);
        self::assertStringContainsString('Timeline', $response['body']);
        self::assertStringContainsString('bootstrap start', $response['body']);
        self::assertStringContainsString('route matched', $response['body']);
        self::assertStringContainsString('use-case executed', $response['body']);
        self::assertStringContainsString('render start', $response['body']);
        self::assertStringContainsString('render end', $response['body']);
        self::assertStringNotContainsString('password', $response['body']);
    }

    public function testPostsReadJsonPostAndPutBodies(): void
    {
        $this->login();
        $response = $this->request('POST', '/api/posts', ['description' => 'Created through JSON']);
        self::assertSame(201, $response['status']);
        $postId = $this->decodeResponse($response)['data']['id'];
        $response = $this->request('PUT', sprintf('/api/post/%s', $postId), ['description' => 'Updated through JSON']);
        self::assertSame(200, $response['status']);
        self::assertSame('Updated through JSON', $this->database->query('SELECT description FROM posts')->fetchColumn());
        $response = $this->request('DELETE', sprintf('/api/post/%s', $postId));
        self::assertSame(204, $response['status']);
        self::assertSame('', $response['body']);
    }

    public function testPostsRejectMalformedJsonWithoutWriting(): void
    {
        $this->login();
        $response = $this->request('POST', '/api/posts', [], '{"description":');
        self::assertSame(422, $response['status']);
        self::assertSame('Malformed JSON body.', $this->decodeResponse($response)['message']);
        self::assertSame(0, (int) $this->database->query('SELECT COUNT(*) FROM posts')->fetchColumn());
    }

    public function testAuthenticatedPostsRequireCsrf(): void
    {
        $this->login();
        $this->csrfToken = '';
        $response = $this->request('POST', '/api/posts', ['description' => 'Must not be created']);
        self::assertSame(403, $response['status']);
        self::assertSame(0, (int) $this->database->query('SELECT COUNT(*) FROM posts')->fetchColumn());
    }

    public function testPostsRejectArrayDescriptions(): void
    {
        $this->login();
        $response = $this->request('POST', '/api/posts', [], '{"description":[]}');
        self::assertSame(422, $response['status']);
        self::assertSame('error', $this->decodeResponse($response)['status']);
    }

    private function login(): void
    {
        $credentials = ['name' => 'Post Author', 'email' => 'author@example.test', 'password' => 'valid-password'];
        self::assertSame(201, $this->request('POST', '/user/create', $credentials)['status']);
        self::assertSame(200, $this->request('POST', '/login', $credentials)['status']);
    }

    private function stopServer(): void
    {
        if ($this->serverProcess === null) {
            return;
        }
        proc_terminate($this->serverProcess);
        foreach ($this->serverPipes as $pipe) {
            fclose($pipe);
        }
        proc_close($this->serverProcess);
        $this->serverProcess = null;
        $this->serverPipes = [];
    }

    private function startServer(string $debug = 'false'): void
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);

        if ($socket === false) {
            throw new \RuntimeException('Unable to reserve a local port for HTTP tests.');
        }

        $socketAddress = stream_socket_get_name($socket, false);
        fclose($socket);

        if ($socketAddress === false) {
            throw new \RuntimeException('Unable to determine the local HTTP test port.');
        }

        $separatorPosition = strrpos($socketAddress, ':');

        if ($separatorPosition === false) {
            throw new \RuntimeException('Unable to parse the local HTTP test port.');
        }

        $this->serverPort = (int) substr($socketAddress, $separatorPosition + 1);
        $environment = getenv();
        $environment['DB_CONNECTION'] = 'sqlite';
        $environment['DB_DATABASE'] = $this->databasePath;
        $environment['DB_HOST'] = '';
        $environment['DB_PORT'] = '';
        $environment['DB_USERNAME'] = '';
        $environment['DB_PASSWORD'] = '';
        $environment['APP_DEBUG'] = $debug;
        $routerPath = sprintf('%s/tests/Feature/HttpTestRouter.php', $this->projectRoot);
        $publicPath = sprintf('%s/public', $this->projectRoot);
        $command = [PHP_BINARY, '-d', 'display_errors=0', '-d', sprintf('session.save_path=%s', $this->sessionPath), '-S', sprintf('127.0.0.1:%d', $this->serverPort), '-t', $publicPath, $routerPath];
        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes, $this->projectRoot, $environment);

        if ($process === false) {
            throw new \RuntimeException('Unable to start the local HTTP test server.');
        }

        $this->serverProcess = $process;
        $this->serverPipes = $pipes;

        foreach ($this->serverPipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        $this->waitForServer();
    }

    private function waitForServer(): void
    {
        $deadline = microtime(true) + 5.0;

        while (true) {
            $now = microtime(true);

            if ($now >= $deadline) {
                break;
            }

            $address = sprintf('tcp://127.0.0.1:%d', $this->serverPort);
            $connection = @stream_socket_client($address, $errorCode, $errorMessage, 0.1);

            if ($connection !== false) {
                fclose($connection);
                return;
            }

            $processStatus = proc_get_status($this->serverProcess);

            if (!$processStatus['running']) {
                throw new \RuntimeException('The local HTTP test server exited before becoming ready.');
            }

            usleep(10000);
        }

        throw new \RuntimeException('The local HTTP test server did not become ready.');
    }

    /**
     * @param array<string, string> $payload
     * @return array{status: int, body: string}
     */
    private function request(string $method, string $path, array $payload = [], ?string $rawBody = null): array
    {
        $requestBody = '';
        $requestHeaders = "Accept: application/json\r\n";

        if ($this->cookie !== '') {
            $requestHeaders .= sprintf("Cookie: %s\r\n", $this->cookie);
        }

        if ($this->csrfToken !== '') {
            $requestHeaders .= sprintf("X-CSRF-Token: %s\r\n", $this->csrfToken);
        }

        if ($method !== 'GET') {
            $encodedBody = json_encode($payload, JSON_THROW_ON_ERROR);
            $requestBody = $rawBody ?? $encodedBody;
            $requestHeaders = sprintf("Content-Type: application/json\r\n%s", $requestHeaders);
        }

        $context = stream_context_create([
            'http' => [
                'method' => $method,
                'header' => $requestHeaders,
                'content' => $requestBody,
                'ignore_errors' => true,
                'timeout' => 3.0,
            ],
        ]);
        $url = sprintf('http://127.0.0.1:%d%s', $this->serverPort, $path);
        $httpStream = fopen($url, 'r', false, $context);

        if ($httpStream === false) {
            throw new \RuntimeException('The local HTTP request could not be opened.');
        }

        return $this->readResponse($httpStream);
    }

    /**
     * @param resource $httpStream
     * @return array{status: int, body: string}
     */
    private function readResponse($httpStream): array
    {
        $metadata = stream_get_meta_data($httpStream);
        $body = stream_get_contents($httpStream);
        fclose($httpStream);

        if ($body === false) {
            throw new \RuntimeException('The local HTTP request returned no response body.');
        }

        $responseHeaders = $metadata['wrapper_data'] ?? [];
        $responseHeadersAreArray = is_array($responseHeaders);

        if (!$responseHeadersAreArray) {
            throw new \RuntimeException('The local HTTP response did not include headers.');
        }

        /** @var array<int, string> $responseHeaders */
        foreach ($responseHeaders as $header) {
            if (preg_match('/^Set-Cookie: ([^;]+)/i', $header, $cookieMatch) === 1) {
                $this->cookie = $cookieMatch[1];
            }
        }

        $statusLine = $responseHeaders[0] ?? '';
        $matchedStatus = preg_match('/\s([0-9]{3})\s/', $statusLine, $matches);

        if ($matchedStatus !== 1) {
            throw new \RuntimeException('The local HTTP response did not include a status code.');
        }

        return ['status' => (int) $matches[1], 'body' => $body];
    }

    /**
     * @param array{status: int, body: string} $response
     * @return array<string, mixed>
     */
    private function decodeResponse(array $response): array
    {
        $payload = json_decode($response['body'], true, 512, JSON_THROW_ON_ERROR);
        $payloadIsArray = is_array($payload);

        if (!$payloadIsArray) {
            throw new \RuntimeException('The local HTTP response was not a JSON object.');
        }

        /** @var array<string, mixed> $payload */
        return $payload;
    }
}
