<?php

declare(strict_types=1);

use Ordain\Delegation\Exceptions\DelegationException;

describe('DelegationException', function (): void {
    it('creates exception with message', function (): void {
        $exception = DelegationException::create('Something went wrong');

        expect($exception->getMessage())->toBe('Something went wrong')
            ->and($exception->getContext())->toBe([]);
    });

    it('creates exception with message and context', function (): void {
        $context = ['user_id' => 1, 'action' => 'delegate'];

        $exception = DelegationException::create('Something went wrong', $context);

        expect($exception->getMessage())->toBe('Something went wrong')
            ->and($exception->getContext())->toBe($context);
    });

    it('is an instance of Exception', function (): void {
        $exception = DelegationException::create('Error');

        expect($exception)->toBeInstanceOf(Exception::class);
    });

    it('stores context with various types', function (): void {
        $context = [
            'string_value' => 'test',
            'int_value' => 42,
            'bool_value' => true,
            'null_value' => null,
        ];

        $exception = DelegationException::create('Error', $context);

        expect($exception->getContext())->toBe($context);
    });
});
