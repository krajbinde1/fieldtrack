<?php

namespace App\Enums;

enum CenterExpenseCategory: string
{
    case BuildingRent = 'building_rent';
    case StaffSalary = 'staff_salary';
    case ElectricityUtilities = 'electricity_utilities';
    case FoodMess = 'food_mess';
    case HostelExpenses = 'hostel_expenses';
    case TrainingMaterials = 'training_materials';
    case UniformStudentKits = 'uniform_student_kits';
    case OfficeExpenses = 'office_expenses';
    case InternetCommunication = 'internet_communication';
    case TravelConveyance = 'travel_conveyance';
    case MobilizationExpenses = 'mobilization_expenses';
    case AssessmentCertification = 'assessment_certification';
    case RepairsMaintenance = 'repairs_maintenance';
    case HousekeepingHygiene = 'housekeeping_hygiene';
    case MedicalSafety = 'medical_safety';
    case StudentActivities = 'student_activities';
    case PlacementExpenses = 'placement_expenses';
    case EquipmentFurniture = 'equipment_furniture';
    case LegalCompliance = 'legal_compliance';
    case MiscellaneousExpenses = 'miscellaneous_expenses';

    public function label(): string
    {
        return match ($this) {
            self::BuildingRent => 'Building Rent',
            self::StaffSalary => 'Staff Salary',
            self::ElectricityUtilities => 'Electricity & Utilities',
            self::FoodMess => 'Food & Mess',
            self::HostelExpenses => 'Hostel Expenses',
            self::TrainingMaterials => 'Training Materials',
            self::UniformStudentKits => 'Uniform & Student Kits',
            self::OfficeExpenses => 'Office Expenses',
            self::InternetCommunication => 'Internet & Communication',
            self::TravelConveyance => 'Travel & Conveyance',
            self::MobilizationExpenses => 'Mobilization Expenses',
            self::AssessmentCertification => 'Assessment & Certification',
            self::RepairsMaintenance => 'Repairs & Maintenance',
            self::HousekeepingHygiene => 'Housekeeping & Hygiene',
            self::MedicalSafety => 'Medical & Safety',
            self::StudentActivities => 'Student Activities',
            self::PlacementExpenses => 'Placement Expenses',
            self::EquipmentFurniture => 'Equipment & Furniture',
            self::LegalCompliance => 'Legal & Compliance',
            self::MiscellaneousExpenses => 'Miscellaneous Expenses',
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
}
