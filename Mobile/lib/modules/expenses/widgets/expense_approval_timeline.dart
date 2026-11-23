import 'package:flutter/material.dart';

import '../../../core/design/app_colors.dart';

class ExpenseTimelineStage {
  const ExpenseTimelineStage({
    required this.keyName,
    required this.title,
    required this.status,
    required this.statusLabel,
    required this.color,
    this.personName,
    this.role,
    this.date,
    this.time,
    this.remarks,
    this.detail,
    this.pendingNames = const [],
  });

  final String keyName;
  final String title;
  final String status;
  final String statusLabel;
  final String color;
  final String? personName;
  final String? role;
  final String? date;
  final String? time;
  final String? remarks;
  final String? detail;
  final List<String> pendingNames;

  Color get tone => switch (color) {
    'green' => AppColors.success,
    'orange' => AppColors.warning,
    'red' => AppColors.error,
    _ => const Color(0xFF9CA3AF),
  };

  Color get badgeBackground => switch (color) {
    'green' => AppColors.approvedBg,
    'orange' => AppColors.pendingBg,
    'red' => AppColors.rejectedBg,
    _ => const Color(0xFFF3F4F6),
  };

  Color get badgeForeground => switch (color) {
    'green' => AppColors.approvedFg,
    'orange' => AppColors.pendingFg,
    'red' => AppColors.rejectedFg,
    _ => const Color(0xFF6B7280),
  };

  factory ExpenseTimelineStage.fromJson(Map<String, dynamic> json) {
    return ExpenseTimelineStage(
      keyName: '${json['key'] ?? ''}',
      title: '${json['title'] ?? ''}',
      status: '${json['status'] ?? ''}',
      statusLabel: '${json['status_label'] ?? ''}',
      color: '${json['color'] ?? 'grey'}',
      personName: _text(json['person_name']),
      role: _text(json['role']),
      date: _text(json['date']),
      time: _text(json['time']),
      remarks: _text(json['remarks']),
      detail: _text(json['detail']),
      pendingNames: (json['pending_names'] as List? ?? const [])
          .map((name) => '$name'.trim())
          .where((name) => name.isNotEmpty)
          .toList(),
    );
  }

  static String? _text(dynamic value) {
    final text = value?.toString().trim() ?? '';
    return text.isEmpty ? null : text;
  }
}

class ExpenseApprovalTimeline extends StatelessWidget {
  const ExpenseApprovalTimeline({
    super.key,
    required this.stages,
    this.pendingText,
  });

  final List<ExpenseTimelineStage> stages;
  final String? pendingText;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          'Approval timeline',
          style: Theme.of(context).textTheme.titleMedium,
        ),
        if (pendingText != null && pendingText!.trim().isNotEmpty) ...[
          const SizedBox(height: 8),
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
            decoration: BoxDecoration(
              color: AppColors.pendingBg,
              borderRadius: BorderRadius.circular(10),
            ),
            child: Text(
              pendingText!,
              style: const TextStyle(
                color: AppColors.pendingFg,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
        const SizedBox(height: 12),
        for (var index = 0; index < stages.length; index++)
          _TimelineRow(
            stage: stages[index],
            isLast: index == stages.length - 1,
          ),
      ],
    );
  }
}

class _TimelineRow extends StatelessWidget {
  const _TimelineRow({required this.stage, required this.isLast});

  final ExpenseTimelineStage stage;
  final bool isLast;

  @override
  Widget build(BuildContext context) {
    final when = [
      stage.date,
      stage.time,
    ].whereType<String>().where((part) => part.isNotEmpty).join(' · ');

    return Stack(
      children: [
        if (!isLast)
          Positioned(
            left: 13,
            top: 32,
            bottom: 0,
            child: Container(
              width: 2,
              color: stage.color == 'green' || stage.color == 'red'
                  ? stage.tone
                  : const Color(0xFFD1D5DB),
            ),
          ),
        Row(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Container(
              key: Key('expense-timeline-dot-${stage.keyName}'),
              width: 28,
              height: 28,
              decoration: BoxDecoration(
                color: stage.color == 'grey' ? Colors.white : stage.tone,
                shape: BoxShape.circle,
                border: stage.color == 'grey'
                    ? Border.all(color: stage.tone, width: 2)
                    : null,
              ),
              alignment: Alignment.center,
              child: stage.color == 'grey'
                  ? Container(
                      width: 8,
                      height: 8,
                      decoration: BoxDecoration(
                        color: stage.tone,
                        shape: BoxShape.circle,
                      ),
                    )
                  : Icon(_iconFor(stage.color), size: 16, color: Colors.white),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Padding(
                padding: EdgeInsets.only(bottom: isLast ? 0 : 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Row(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Expanded(
                          child: Text(
                            stage.title,
                            style: const TextStyle(fontWeight: FontWeight.w700),
                          ),
                        ),
                        const SizedBox(width: 8),
                        Container(
                          key: Key('expense-timeline-badge-${stage.keyName}'),
                          padding: const EdgeInsets.symmetric(
                            horizontal: 8,
                            vertical: 4,
                          ),
                          decoration: BoxDecoration(
                            color: stage.badgeBackground,
                            borderRadius: BorderRadius.circular(999),
                          ),
                          child: Text(
                            stage.statusLabel,
                            style: TextStyle(
                              color: stage.badgeForeground,
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ],
                    ),
                    if (stage.personName != null)
                      Text(
                        stage.personName!,
                        style: const TextStyle(fontWeight: FontWeight.w600),
                      ),
                    if (stage.role != null)
                      Text(
                        stage.role!,
                        style: const TextStyle(
                          color: AppColors.textSecondary,
                          fontSize: 13,
                        ),
                      ),
                    if (when.isNotEmpty)
                      Text(
                        when,
                        style: const TextStyle(
                          color: AppColors.textSecondary,
                          fontSize: 13,
                        ),
                      ),
                    if (stage.detail != null)
                      Text(
                        stage.detail!,
                        style: const TextStyle(
                          color: AppColors.textSecondary,
                          fontSize: 13,
                        ),
                      ),
                    if (stage.remarks != null)
                      Text(
                        stage.remarks!,
                        style: const TextStyle(
                          color: AppColors.textSecondary,
                          fontSize: 13,
                        ),
                      ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ],
    );
  }

  IconData _iconFor(String color) {
    return switch (color) {
      'green' => Icons.check,
      'red' => Icons.close,
      'orange' => Icons.schedule,
      _ => Icons.circle,
    };
  }
}
