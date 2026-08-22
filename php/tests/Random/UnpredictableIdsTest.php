<?php

namespace diCore\Tests\Random;

use diCore\Entity\User\Model as User;
use diCore\Helper\StringHelper;
use PHPUnit\Framework\TestCase;

/**
 * get_unique_id() and StringHelper::random() mint file names, activation keys and
 * session tokens. The former implementations drew them from mt_rand, and
 * get_unique_id() additionally called srand() with a 10^6 seed, poisoning the
 * generator for the rest of the request. These tests fail on exactly that.
 */
class UnpredictableIdsTest extends TestCase
{
    /** A token reproducible from a known seed is brute-forceable */
    public function testRandomStringIsNotReproducibleFromSeed(): void
    {
        mt_srand(42);
        $first = StringHelper::random(32);

        mt_srand(42);
        $second = StringHelper::random(32);

        $this->assertNotSame(
            $first,
            $second,
            'random() must not depend on the mt_rand state'
        );
    }

    public function testUniqueIdIsNotReproducibleFromSeed(): void
    {
        mt_srand(42);
        $first = get_unique_id();

        mt_srand(42);
        $second = get_unique_id();

        $this->assertNotSame($first, $second);
    }

    /**
     * srand() inside get_unique_id() broke not so much the function itself as
     * everything drawing randomness from the same generator later on.
     */
    public function testUniqueIdDoesNotDisturbSeededStream(): void
    {
        mt_srand(1917);
        $expected = [mt_rand(), mt_rand(), mt_rand()];

        mt_srand(1917);
        get_unique_id();
        $actual = [mt_rand(), mt_rand(), mt_rand()];

        $this->assertSame(
            $expected,
            $actual,
            'get_unique_id() must not re-seed mt_rand'
        );
    }

    /**
     * 2000 values out of a 10^6 space collide with ~86% probability (birthday
     * paradox); out of 2^128 they never do.
     */
    public function testUniqueIdsDoNotCollide(): void
    {
        $ids = [];

        for ($i = 0; $i < 2000; $i++) {
            $ids[get_unique_id()] = true;
        }

        $this->assertCount(2000, $ids);
    }

    public function testUniqueIdShape(): void
    {
        $this->assertMatchesRegularExpression('/^[0-9a-f]{32}$/', get_unique_id());
        $this->assertSame(16, strlen(get_unique_id(16)));
        $this->assertSame(32, strlen(get_unique_id(32)));
        $this->assertSame(32, strlen(get_unique_id(0)), '0 means full length');
        $this->assertSame(32, strlen(get_unique_id(64)), 'over 32 does not stretch it');
    }

    /** MIN_PASSWORD_LENGTH is a human threshold, not a generator's measure */
    public function testGeneratedPasswordIsLongerThanTheHumanMinimum(): void
    {
        $password = User::generatePassword();

        $this->assertSame(User::GENERATED_PASSWORD_LENGTH, strlen($password));
        $this->assertGreaterThan(User::MIN_PASSWORD_LENGTH, strlen($password));
    }

    public function testRandomStringShapeAndAlphabet(): void
    {
        $this->assertSame(8, strlen(StringHelper::random()));
        $this->assertMatchesRegularExpression(
            '/^[0-9a-zA-Z]{24}$/',
            StringHelper::random(24)
        );
    }
}
