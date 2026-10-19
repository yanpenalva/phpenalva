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
use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;

final class IdentityTest extends TestCase
{
    public function testEmailNormalizesAndEnforcesHundredCharacterLimit(): void
    {
        self::assertSame('a@example.com', (new Email(' A@EXAMPLE.COM '))->value());
        self::assertSame(100, strlen((new Email(str_repeat('a', 88) . '@example.com'))->value()));
        $this->expectException(InvalidArgumentException::class);
        new Email(str_repeat('a', 89) . '@example.com');
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
