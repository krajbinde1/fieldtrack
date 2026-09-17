class FieldActivity {
  const FieldActivity({
    required this.id,
    required this.activityType,
    required this.activityName,
    this.employeeId,
    this.employeeName,
    this.staffRoleLabel,
    this.centerId,
    this.centerName,
    this.activityTypeLabel,
    this.activityAt,
    this.activityDate,
    this.activityTime,
    this.remarks,
    this.photoUrl,
    this.latitude,
    this.longitude,
    this.location,
    this.mapsUrl,
  });

  final int id;
  final int? employeeId;
  final String? employeeName;
  final String? staffRoleLabel;
  final int? centerId;
  final String? centerName;
  final String activityType;
  final String? activityTypeLabel;
  final String activityName;
  final DateTime? activityAt;
  final String? activityDate;
  final String? activityTime;
  final String? remarks;
  final String? photoUrl;
  final double? latitude;
  final double? longitude;
  final String? location;
  final String? mapsUrl;

  factory FieldActivity.fromJson(Map<String, dynamic> json) {
    return FieldActivity(
      id: int.tryParse('${json['id']}') ?? 0,
      employeeId: int.tryParse('${json['employee_id'] ?? ''}'),
      employeeName: json['employee_name']?.toString(),
      staffRoleLabel: json['staff_role_label']?.toString(),
      centerId: int.tryParse('${json['center_id'] ?? ''}'),
      centerName: json['center_name']?.toString(),
      activityType: json['activity_type']?.toString() ?? 'other',
      activityTypeLabel: json['activity_type_label']?.toString(),
      activityName:
          json['activity_name']?.toString() ??
          json['activity_type_label']?.toString() ??
          'Activity',
      activityAt: DateTime.tryParse('${json['activity_at'] ?? ''}'),
      activityDate: json['activity_date']?.toString(),
      activityTime: json['activity_time']?.toString(),
      remarks: json['remarks']?.toString(),
      photoUrl: json['photo_url']?.toString(),
      latitude: double.tryParse('${json['latitude'] ?? ''}'),
      longitude: double.tryParse('${json['longitude'] ?? ''}'),
      location: json['location']?.toString(),
      mapsUrl: json['maps_url']?.toString(),
    );
  }

  String get displayName =>
      activityName.trim().isEmpty ? (activityTypeLabel ?? 'Activity') : activityName;

  String get whenLabel {
    final date = activityDate;
    final time = activityTime;
    if ((date ?? '').isNotEmpty && (time ?? '').isNotEmpty) {
      return '$date · $time';
    }
    if ((date ?? '').isNotEmpty) return date!;
    if (activityAt == null) return '—';
    return activityAt!.toIso8601String();
  }
}

class FieldActivityEmployeeOption {
  const FieldActivityEmployeeOption({required this.id, required this.name});

  final int id;
  final String name;

  factory FieldActivityEmployeeOption.fromJson(Map<String, dynamic> json) {
    return FieldActivityEmployeeOption(
      id: int.tryParse('${json['id']}') ?? 0,
      name: json['full_name']?.toString() ?? 'Employee',
    );
  }
}

class FieldActivityListResult {
  const FieldActivityListResult({
    required this.items,
    this.employees = const [],
  });

  final List<FieldActivity> items;
  final List<FieldActivityEmployeeOption> employees;
}
