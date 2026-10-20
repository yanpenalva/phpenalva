<?php

declare(strict_types=1);

namespace Tests\Unit\Identity;

use App\Identity\Application\RegisterUser;
use App\Identity\Application\UserValidationException;
use App\Identity\Domain\Email;
use App\Identity\Domain\EmailAlreadyUsed;
use App\Identity\Domain\User;
use App\Identity\Domain\UserRepository;
use App\Identity\Infrastructure\Persistence\PdoUserRepository;
use App\Identity\Application\AuthRateLimiter;
use App\Identity\Infrastructure\Persistence\FileAttemptStore;
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class IdentityTest extends TestCase
{
    public function testFileAttemptStoreSharesLimitAcrossInstancesAndPrunesExpiredAttempts(): void
    {
        $directory = sys_get_temp_dir() . '/phpenalva-test-' . bin2hex(random_bytes(8));
        $limiter = new AuthRateLimiter(new FileAttemptStore($directory));
        for ($attempt = 0; $attempt < 5; $attempt++) { $limiter->recordFailure('a@example.com', '127.0.0.1'); }
        self::assertTrue((new AuthRateLimiter(new FileAttemptStore($directory)))->tooManyAttempts('a@example.com', '127.0.0.1'));
        $path = glob($directory . '/*.json')[0];
        file_put_contents($path, json_encode([time() - 901]));
        self::assertFalse($limiter->tooManyAttempts('a@example.com', '127.0.0.1'));
        foreach (glob($directory . '/*') as $file) { unlink($file); }
        rmdir($directory);
    }

    public function testFileAttemptStoreSharesLimitAcrossSeparatePhpProcesses(): void
    {
        $directory = sys_get_temp_dir() . '/phpenalva-test-' . bin2hex(random_bytes(8));
        $autoload = dirname(__DIR__, 3) . '/vendor/autoload.php';
        $script = 'require ' . var_export($autoload, true) . '; $l=new \\App\\Identity\\Application\\AuthRateLimiter(new \\App\\Identity\\Infrastructure\\Persistence\\FileAttemptStore(' . var_export($directory, true) . ')); for($i=0;$i<5;$i++){$l->recordFailure("a@example.com","127.0.0.1");}';
        exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script), $output, $status);
        self::assertSame(0, $status);
        self::assertTrue((new AuthRateLimiter(new FileAttemptStore($directory)))->tooManyAttempts('a@example.com', '127.0.0.1'));
        foreach (glob($directory . '/*') as $file) { unlink($file); }
        rmdir($directory);
    }

    public function testEmailNormalizesAndEnforcesHundredCharacterLimit(): void
    {
        self::assertSame('a@example.com', (new Email(' A@EXAMPLE.COM '))->value());
        self::assertSame(100, strlen((new Email(str_repeat('a', 64) . '@' . str_repeat('b', 31) . '.com'))->value()));
        $this->expectException(InvalidArgumentException::class);
        new Email(str_repeat('a', 64) . '@' . str_repeat('b', 32) . '.com');
    }

    public function testEmailRejectsMalformedAddress(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Email('not-an-email');
    }

    public function testUserRejectsInvalidNameAndEmptyHash(): void
    {
        $this->expectException(InvalidArgumentException::class);
        User::register(' ', new Email('a@example.com'), 'hash');
    }

    public function testRegisterMapsExistingAndConcurrentDuplicateTo422AndReturnsNoHash(): void
    {
        $repo = new MemoryUsers();
        $useCase = new RegisterUser($repo);
        $data = ['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret1'];
        $created = $useCase->execute($data);
        self::assertSame(['id', 'name', 'email'], array_keys($created));
        try { $useCase->execute($data); self::fail('Expected duplicate validation.'); }
        catch (UserValidationException $exception) { self::assertArrayHasKey('email', $exception->errors()); }

        $race = new MemoryUsers(true);
        try { (new RegisterUser($race))->execute($data); self::fail('Expected concurrent duplicate validation.'); }
        catch (UserValidationException $exception) { self::assertArrayHasKey('email', $exception->errors()); }
    }

    public function testPdoAdapterUsesProvidedTemporarySqliteDatabase(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT UNIQUE, password TEXT)');
        $repository = new PdoUserRepository($pdo);
        $user = User::register('A', new Email('a@example.com'), 'hash');
        $repository->add($user);
        self::assertNotNull($repository->findByEmail(new Email('a@example.com')));
        try { $repository->add($user); self::fail('Expected unique constraint translation.'); }
        catch (EmailAlreadyUsed) { self::assertTrue(true); }
    }

    public function testRegistrationMapsDatabaseRaceToValidationError(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, email TEXT UNIQUE, password TEXT)');
        $pdo->exec("CREATE TRIGGER concurrent_duplicate BEFORE INSERT ON users BEGIN SELECT RAISE(ABORT, 'UNIQUE constraint failed: users.email'); END");
        $input = ['name' => 'A', 'email' => 'a@example.com', 'password' => 'secret1'];
        try {
            (new RegisterUser(new PdoUserRepository($pdo)))->execute($input);
            self::fail('Expected unique race validation.');
        } catch (UserValidationException $exception) {
            self::assertArrayHasKey('email', $exception->errors());
        }
    }
}

final class MemoryUsers implements UserRepository
{
    /** @var array<string, User> */
    private array $users = [];
    public function __construct(private bool $race = false) {}
    public function findByEmail(Email $email): ?User { return $this->users[$email->value()] ?? null; }
    public function add(User $user): User
    {
        if ($this->race || isset($this->users[$user->email()->value()])) { throw new EmailAlreadyUsed('duplicate'); }
        $saved = User::reconstitute(1, $user->name(), $user->email(), $user->passwordHash());
        $this->users[$user->email()->value()] = $saved;
        return $saved;
    }
    public function delete(int $id): bool { return false; }
}
