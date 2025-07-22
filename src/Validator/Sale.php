<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

#[\Attribute(\Attribute::TARGET_CLASS)]
class Sale extends Constraint
{
    public $message = 'The RUT "{{ rut }}" is not valid.';
    public $clientNotFoundMessage = 'The client with RUT "{{ rut }}" does not exist.';
    public $folioExistsMessage = 'The folio number "{{ folio }}" already exists in the current contingency.';

    public function __construct(
        public string $mode = 'strict',
        ?array $groups = null,
        mixed $payload = null
    ) {
        parent::__construct([], $groups, $payload);
    }
}
