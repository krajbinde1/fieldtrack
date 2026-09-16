import '../../../core/widgets/design/pg_status_badge.dart';

class NamedLookup {
  const NamedLookup({required this.id, required this.name, this.code});

  final int id;
  final String name;
  final String? code;

  factory NamedLookup.fromJson(Map<String, dynamic> json) => NamedLookup(
    id: _asInt(json['id']),
    name: json['name']?.toString() ?? '',
    code: json['code']?.toString(),
  );

  static int _asInt(Object? value) {
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse('$value') ?? 0;
  }
}

class AdmissionDocumentInfo {
  const AdmissionDocumentInfo({
    required this.id,
    required this.documentType,
    required this.label,
    required this.originalName,
    this.mimeType,
    this.size,
  });

  final int id;
  final String documentType;
  final String label;
  final String originalName;
  final String? mimeType;
  final int? size;

  factory AdmissionDocumentInfo.fromJson(Map<String, dynamic> json) =>
      AdmissionDocumentInfo(
        id: json['id'] as int,
        documentType: json['document_type']?.toString() ?? '',
        label: json['document_type_label']?.toString() ??
            json['document_type']?.toString() ??
            'Document',
        originalName: json['original_name']?.toString() ?? 'file',
        mimeType: json['mime_type']?.toString(),
        size: json['size'] is int ? json['size'] as int : int.tryParse('${json['size']}'),
      );

  bool get isImage =>
      (mimeType ?? '').startsWith('image/') ||
      originalName.toLowerCase().endsWith('.jpg') ||
      originalName.toLowerCase().endsWith('.jpeg') ||
      originalName.toLowerCase().endsWith('.png') ||
      originalName.toLowerCase().endsWith('.webp');
}

class AdmissionRecord {
  const AdmissionRecord({
    required this.id,
    required this.status,
    required this.statusLabel,
    required this.currentStep,
    required this.editable,
    this.schemeId,
    this.schemeName,
    this.firstName,
    this.middleName,
    this.lastName,
    this.fullName,
    this.gender,
    this.religion,
    this.caste,
    this.state = 'Maharashtra',
    this.districtId,
    this.districtName,
    this.talukaId,
    this.talukaName,
    this.village,
    this.documents = const [],
    this.projectName,
    this.centerName,
    this.employeeName,
    this.submittedAt,
    this.updatedAt,
    this.reviewReason,
    this.reviewedAt,
    this.confirmedAt,
    this.confirmedBy,
    this.reviewedByUserId,
    this.canConfirm = false,
    this.canRevert = false,
    this.canReject = false,
  });

  final int id;
  final String status;
  final String statusLabel;
  final int currentStep;
  final bool editable;
  final int? schemeId;
  final String? schemeName;
  final String? firstName;
  final String? middleName;
  final String? lastName;
  final String? fullName;
  final String? gender;
  final String? religion;
  final String? caste;
  final String state;
  final int? districtId;
  final String? districtName;
  final int? talukaId;
  final String? talukaName;
  final String? village;
  final List<AdmissionDocumentInfo> documents;
  final String? projectName;
  final String? centerName;
  final String? employeeName;
  final String? submittedAt;
  final String? updatedAt;
  final String? reviewReason;
  final String? reviewedAt;
  final String? confirmedAt;
  final int? confirmedBy;
  final int? reviewedByUserId;
  final bool canConfirm;
  final bool canRevert;
  final bool canReject;

  bool get isDraft => _statusKey == 'draft';
  bool get isSubmitted => _statusKey == 'submitted' || _labelKey == 'submitted';
  bool get isConfirmed => _statusKey == 'confirmed';
  bool get isReverted => _statusKey == 'reverted';
  bool get isRejected => _statusKey == 'rejected';
  bool get canReview => canConfirm || canRevert || canReject || isSubmitted;

  String get _statusKey => status.trim().toLowerCase();
  String get _labelKey => statusLabel.trim().toLowerCase();

  PgStatusTone get statusTone {
    if (isConfirmed) return PgStatusTone.approved;
    if (isRejected) return PgStatusTone.rejected;
    if (isSubmitted) return PgStatusTone.info;
    return PgStatusTone.pending;
  }

  String get displayName {
    if (fullName != null && fullName!.trim().isNotEmpty) return fullName!.trim();
    return [firstName, middleName, lastName]
        .where((part) => part != null && part.trim().isNotEmpty)
        .join(' ');
  }

  String get addressLabel => [
    village,
    talukaName,
    districtName,
    state,
  ].where((part) => part != null && part.trim().isNotEmpty).join(', ');

  AdmissionDocumentInfo? documentOf(String type) {
    for (final document in documents) {
      if (document.documentType == type) return document;
    }
    return null;
  }

  factory AdmissionRecord.fromJson(Map<String, dynamic> json) {
    final scheme = json['scheme'];
    final district = json['district'];
    final taluka = json['taluka'];
    final project = json['project'];
    final center = json['center'];
    final employee = json['employee'];
    final documents = json['documents'];

    final status = _normalizeAdmissionStatus(json['status']);
    final editable = json.containsKey('editable')
        ? json['editable'] == true
        : status == 'draft' || status == 'reverted';

    return AdmissionRecord(
      id: json['id'] as int,
      status: status,
      statusLabel: json['status_label']?.toString() ?? 'Draft',
      currentStep: json['current_step'] is int
          ? json['current_step'] as int
          : int.tryParse('${json['current_step']}') ?? 1,
      editable: editable,
      schemeId: scheme is Map ? scheme['id'] as int? : json['scheme_id'] as int?,
      schemeName: scheme is Map ? scheme['name']?.toString() : null,
      firstName: json['first_name']?.toString(),
      middleName: json['middle_name']?.toString(),
      lastName: json['last_name']?.toString(),
      fullName: json['full_name']?.toString(),
      gender: json['gender']?.toString(),
      religion: json['religion']?.toString(),
      caste: json['caste']?.toString(),
      state: json['state']?.toString() ?? 'Maharashtra',
      districtId: district is Map ? district['id'] as int? : json['district_id'] as int?,
      districtName: district is Map ? district['name']?.toString() : null,
      talukaId: taluka is Map ? taluka['id'] as int? : json['taluka_id'] as int?,
      talukaName: taluka is Map ? taluka['name']?.toString() : null,
      village: json['village']?.toString(),
      documents: documents is List
          ? documents
                .whereType<Map>()
                .map((item) => AdmissionDocumentInfo.fromJson(
                      Map<String, dynamic>.from(item),
                    ))
                .toList()
          : const [],
      projectName: project is Map ? project['name']?.toString() : null,
      centerName: center is Map ? center['name']?.toString() : null,
      employeeName: employee is Map ? employee['full_name']?.toString() : null,
      submittedAt: json['submitted_at']?.toString(),
      updatedAt: json['updated_at']?.toString(),
      reviewReason: json['review_reason']?.toString(),
      reviewedAt: json['reviewed_at']?.toString(),
      confirmedAt: json['confirmed_at']?.toString(),
      confirmedBy: _asNullableInt(json['confirmed_by']),
      reviewedByUserId: _asNullableInt(json['reviewed_by_user_id']),
      canConfirm: json['can_confirm'] == true,
      canRevert: json['can_revert'] == true,
      canReject: json['can_reject'] == true,
    );
  }
}

class AdmissionLookups {
  const AdmissionLookups({
    this.state = 'Maharashtra',
    this.districts = const [],
    this.genders = const ['Male', 'Female', 'Other'],
    this.religions = const [
      'Hindu',
      'Muslim',
      'Buddhist',
      'Christian',
      'Sikh',
      'Jain',
      'Other',
    ],
    this.castes = const ['Open', 'OBC', 'SC', 'ST', 'VJNT', 'NT', 'SBC', 'Other'],
  });

  final String state;
  final List<NamedLookup> districts;
  final List<String> genders;
  final List<String> religions;
  final List<String> castes;

  factory AdmissionLookups.fromJson(Map<String, dynamic> json) {
    final districts = json['districts'] ?? json['data'];
    return AdmissionLookups(
      state: json['state']?.toString() ?? 'Maharashtra',
      districts: _lookups(districts),
      genders: _strings(json['genders'], const ['Male', 'Female', 'Other']),
      religions: _strings(json['religions'], const [
        'Hindu',
        'Muslim',
        'Buddhist',
        'Christian',
        'Sikh',
        'Jain',
        'Other',
      ]),
      castes: _strings(json['castes'], const [
        'Open',
        'OBC',
        'SC',
        'ST',
        'VJNT',
        'NT',
        'SBC',
        'Other',
      ]),
    );
  }

  static List<NamedLookup> _lookups(Object? value) {
    if (value is! List || value.isEmpty) return const [];
    return value
        .whereType<Map>()
        .map((item) => NamedLookup.fromJson(Map<String, dynamic>.from(item)))
        .where((item) => item.id > 0 && item.name.trim().isNotEmpty)
        .toList();
  }

  static List<String> _strings(Object? value, List<String> fallback) {
    if (value is! List || value.isEmpty) return fallback;
    return value.map((item) => '$item').toList();
  }
}

int? _asNullableInt(Object? value) {
  if (value == null) return null;
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse('$value');
}

String _normalizeAdmissionStatus(Object? value) {
  final raw = value?.toString().trim().toLowerCase();
  if (raw == null || raw.isEmpty) return 'draft';
  return raw;
}
