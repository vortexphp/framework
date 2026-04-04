<?php

declare(strict_types=1);

namespace Vortex\Auth;

use Vortex\AppContext;

/**
 * Ability callbacks and model policies. Abilities receive the current user (may be null) then any extra arguments.
 *
 * Policy methods must match the ability name (e.g. {@code update} for {@code Gate::allows('update', $post)}).
 */
final class Gate
{
    /** @var array<string, callable(mixed, mixed...): bool> */
    private static array $abilities = [];

    /** @var array<class-string, class-string> class-string (model) => policy class-string */
    private static array $policies = [];

    /**
     * @param callable(mixed, mixed...): bool $callback (user, ...args)
     */
    public static function define(string $ability, callable $callback): void
    {
        self::$abilities[$ability] = $callback;
    }

    /**
     * @param class-string $modelClass
     * @param class-string $policyClass
     */
    public static function policy(string $modelClass, string $policyClass): void
    {
        self::$policies[$modelClass] = $policyClass;
    }

    public static function allows(string $ability, mixed ...$arguments): bool
    {
        $user = Auth::user();

        if (count($arguments) === 1 && is_object($arguments[0])) {
            $model = $arguments[0];
            $policyClass = self::$policies[$model::class] ?? null;
            if ($policyClass !== null && class_exists($policyClass)) {
                $policy = AppContext::container()->make($policyClass);
                if (is_callable([$policy, $ability])) {
                    return (bool) $policy->{$ability}($user, $model);
                }
            }
        }

        $cb = self::$abilities[$ability] ?? null;
        if ($cb !== null) {
            return (bool) $cb($user, ...$arguments);
        }

        return false;
    }

    public static function denies(string $ability, mixed ...$arguments): bool
    {
        return ! self::allows($ability, ...$arguments);
    }

    /**
     * @throws AuthorizationException
     */
    public static function authorize(string $ability, mixed ...$arguments): void
    {
        if (! self::allows($ability, ...$arguments)) {
            throw new AuthorizationException('This action is unauthorized.');
        }
    }

    /**
     * @internal
     */
    public static function resetForTesting(): void
    {
        self::$abilities = [];
        self::$policies = [];
    }
}
