<?php

namespace App\Enums;

enum AdmissionDocumentType: string
{
    case Aadhaar = 'aadhaar';
    case Marksheet = 'marksheet';
    case BankPassbook = 'bank_passbook';
    case CasteCertificate = 'caste_certificate';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Aadhaar => 'Aadhaar Card',
            self::Marksheet => 'Marksheet',
            self::BankPassbook => 'Bank Passbook',
            self::CasteCertificate => 'Caste Certificate',
            self::Other => 'Other Document',
        };
    }

    public function requiredOnSubmit(): bool
    {
        return $this === self::Aadhaar;
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    /**
     * @return list<string>
     */
    public static function requiredOnSubmitValues(): array
    {
        return array_values(array_map(
            static fn (self $type): string => $type->value,
            array_filter(self::cases(), static fn (self $type): bool => $type->requiredOnSubmit()),
        ));
    }
}
