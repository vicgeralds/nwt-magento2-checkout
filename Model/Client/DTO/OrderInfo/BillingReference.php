<?php declare(strict_types=1);

namespace Svea\Checkout\Model\Client\DTO\OrderInfo;

class BillingReference
{
    private string $type = '';

    private string $value = '';

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function getType(): string
    {
        return $this->type ?? '';
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value ?? '';
    }

    public function toArray(): array
    {
        return [
            'type' => $this->getType(),
            'value' => $this->getValue(),
        ];
    }
}
