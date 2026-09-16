class LeaveRecord {
  const LeaveRecord({
    required this.id,
    required this.status,
    required this.statusLabel,
    required this.leaveType,
    required this.leaveTypeLabel,
    required this.fromDate,
    required this.toDate,
    required this.totalDays,
    required this.reason,
    required this.editable,
    required this.cancellable,
    this.approvalRemark,
    this.rejectionRemark,
    this.reviewedBy,
    this.employeeName,
    this.projectName,
    this.centerName,
    this.documentName,
    this.hasDocument = false,
  });

  final int id;
  final String status;
  final String statusLabel;
  final String leaveType;
  final String leaveTypeLabel;
  final String fromDate;
  final String toDate;
  final int totalDays;
  final String reason;
  final bool editable;
  final bool cancellable;
  final String? approvalRemark;
  final String? rejectionRemark;
  final String? reviewedBy;
  final String? employeeName;
  final String? projectName;
  final String? centerName;
  final String? documentName;
  final bool hasDocument;

  bool get isPending => status == 'pending';
  bool get isApproved => status == 'approved';
  bool get isRejected => status == 'rejected';

  factory LeaveRecord.fromJson(Map<String, dynamic> json) {
    final employee = json['employee'];
    final project = json['project'];
    final center = json['center'];
    final document = json['document'];

    return LeaveRecord(
      id: json['id'] as int,
      status: json['status']?.toString() ?? 'pending',
      statusLabel: json['status_label']?.toString() ?? 'Pending',
      leaveType: json['leave_type']?.toString() ?? '',
      leaveTypeLabel: json['leave_type_label']?.toString() ?? '',
      fromDate: json['from_date']?.toString() ?? '',
      toDate: json['to_date']?.toString() ?? '',
      totalDays: json['total_days'] is int
          ? json['total_days'] as int
          : int.tryParse('${json['total_days']}') ?? 0,
      reason: json['reason']?.toString() ?? '',
      editable: json['editable'] == true,
      cancellable: json['cancellable'] == true,
      approvalRemark: json['approval_remark']?.toString(),
      rejectionRemark: json['rejection_remark']?.toString(),
      reviewedBy: json['reviewed_by']?.toString(),
      employeeName: employee is Map ? employee['full_name']?.toString() : null,
      projectName: project is Map ? project['name']?.toString() : null,
      centerName: center is Map ? center['name']?.toString() : null,
      documentName: document is Map ? document['original_name']?.toString() : null,
      hasDocument: document is Map,
    );
  }
}

const leaveTypeOptions = <(String, String)>[
  ('casual', 'Casual Leave'),
  ('sick', 'Sick Leave'),
  ('paid', 'Paid Leave'),
  ('unpaid', 'Unpaid Leave'),
  ('other', 'Other'),
];
