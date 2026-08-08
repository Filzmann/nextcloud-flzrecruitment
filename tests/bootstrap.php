<?php

declare(strict_types=1);

namespace OCP {
    if (!interface_exists(IUser::class)) {
        interface IUser {
            public function getUID(): string;
        }
    }

    if (!interface_exists(IUserSession::class)) {
        interface IUserSession {
            public function getUser(): ?IUser;
        }
    }

    if (!interface_exists(IGroup::class)) {
        interface IGroup {
            public function inGroup(IUser $user): bool;
        }
    }

    if (!interface_exists(IGroupManager::class)) {
        interface IGroupManager {
            public function isAdmin(string $uid): bool;
            public function get(string $gid): ?IGroup;
        }
    }

    if (!interface_exists(IAppConfig::class)) {
        interface IAppConfig {
            public function getValueString(string $appId, string $key, string $default = ''): string;
            public function setValueString(string $appId, string $key, string $value): void;
        }
    }

    if (!interface_exists(IUserManager::class)) {
        interface IUserManager {
            public function userExists(string $uid): bool;
        }
    }
}

namespace RecruitmentTests {
    use Throwable;

    final class TestRunner {
        /** @var list<string> */
        private static array $failures = [];
        private static bool $shutdownRegistered = false;

        public static function test(string $name, callable $test): void {
            self::registerFailureExit();
            try {
                $test();
                fwrite(STDOUT, "ok - {$name}\n");
            } catch (Throwable $error) {
                self::$failures[] = "not ok - {$name}: {$error->getMessage()}";
            }
        }

        /** @return list<string> */
        public static function failures(): array {
            return self::$failures;
        }

        private static function registerFailureExit(): void {
            if (self::$shutdownRegistered) {
                return;
            }
            self::$shutdownRegistered = true;
            register_shutdown_function(static function (): void {
                if (self::$failures === []) {
                    return;
                }
                fwrite(STDERR, implode("\n", self::$failures) . "\n");
                exit(1);
            });
        }
    }

    function assertSame(mixed $expected, mixed $actual, string $message = ''): void {
        if ($expected !== $actual) {
            throw new \RuntimeException($message !== '' ? $message : sprintf(
                'Expected %s, got %s',
                var_export($expected, true),
                var_export($actual, true),
            ));
        }
    }

    function assertTrue(bool $condition, string $message = 'Expected condition to be true'): void {
        if (!$condition) {
            throw new \RuntimeException($message);
        }
    }

    function assertThrows(callable $operation, string $exceptionClass): void {
        try {
            $operation();
        } catch (Throwable $error) {
            if ($error instanceof $exceptionClass) {
                return;
            }
            throw new \RuntimeException("Expected {$exceptionClass}, got " . $error::class);
        }

        throw new \RuntimeException("Expected {$exceptionClass}, but no exception was thrown");
    }
}
