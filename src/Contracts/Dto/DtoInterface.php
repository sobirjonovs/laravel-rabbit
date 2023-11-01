<?php

namespace App\Rabbitmq\Contracts\Dto;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

interface DtoInterface extends Arrayable
{
    /**
     * @return string
     */
    public function toJson(): string;

    /**
     * @return Collection
     */
    public function toCollection(): Collection;

    /**
     * @param array $parameters
     * @return AbstractDataObjectTransfer
     */
    public static function make(array $parameters): AbstractDataObjectTransfer;

    /**
     * @return array
     */
    public function only(): array;

    /**
     * @return bool
     */
    public function isEmpty(): bool;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array;

    /**
     * Get custom messages for validator errors.
     *
     * @return array
     */
    public function messages(): array;
}
