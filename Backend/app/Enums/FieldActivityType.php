<?php

namespace App\Enums;

enum FieldActivityType: string
{
    case VillageVisit = 'village_visit';
    case CommunityMeeting = 'community_meeting';
    case AwarenessCamp = 'awareness_camp';
    case FollowUp = 'follow_up';
    case HouseholdSurvey = 'household_survey';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::VillageVisit => 'Village Visit',
            self::CommunityMeeting => 'Community Meeting',
            self::AwarenessCamp => 'Awareness Camp',
            self::FollowUp => 'Follow-up',
            self::HouseholdSurvey => 'Household Survey',
            self::Other => 'Other',
        };
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

    public static function tryFromMixed(?string $value): self
    {
        return self::tryFrom((string) $value) ?? self::Other;
    }
}
