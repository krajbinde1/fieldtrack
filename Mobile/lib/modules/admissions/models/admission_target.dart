class AdmissionTargetSummary {
  const AdmissionTargetSummary({
    required this.target,
    required this.achieved,
    required this.remaining,
    required this.percentage,
    this.preset = 'this_week',
    this.from,
    this.to,
  });

  final int target;
  final int achieved;
  final int remaining;
  final double percentage;
  final String preset;
  final String? from;
  final String? to;

  factory AdmissionTargetSummary.fromJson(Map<String, dynamic> json) {
    final period = json['period'];
    return AdmissionTargetSummary(
      target: _asInt(json['target']),
      achieved: _asInt(json['achieved']),
      remaining: _asInt(json['remaining']),
      percentage: _asDouble(json['percentage']),
      preset: period is Map
          ? (period['preset']?.toString() ?? 'this_week')
          : 'this_week',
      from: period is Map ? period['from']?.toString() : null,
      to: period is Map ? period['to']?.toString() : null,
    );
  }

  static const empty = AdmissionTargetSummary(
    target: 0,
    achieved: 0,
    remaining: 0,
    percentage: 0,
  );
}

class AdmissionTargetSplit {
  const AdmissionTargetSplit({
    required this.targetCount,
    required this.achieved,
    required this.remaining,
    required this.percentage,
    this.id,
    this.periodStart,
    this.periodEnd,
  });

  final int? id;
  final String? periodStart;
  final String? periodEnd;
  final int targetCount;
  final int achieved;
  final int remaining;
  final double percentage;

  factory AdmissionTargetSplit.fromJson(Map<String, dynamic> json) =>
      AdmissionTargetSplit(
        id: _asInt(json['id']) == 0 ? null : _asInt(json['id']),
        periodStart: json['period_start']?.toString(),
        periodEnd: json['period_end']?.toString(),
        targetCount: _asInt(json['target_count']),
        achieved: _asInt(json['achieved']),
        remaining: _asInt(json['remaining']),
        percentage: _asDouble(json['percentage']),
      );
}

class AdmissionTargetRecord {
  const AdmissionTargetRecord({
    required this.id,
    required this.targetType,
    required this.targetTypeLabel,
    required this.targetCount,
    required this.achieved,
    required this.remaining,
    required this.percentage,
    this.periodStart,
    this.periodEnd,
    this.weeklySplits = const [],
  });

  final int id;
  final String targetType;
  final String targetTypeLabel;
  final String? periodStart;
  final String? periodEnd;
  final int targetCount;
  final int achieved;
  final int remaining;
  final double percentage;
  final List<AdmissionTargetSplit> weeklySplits;

  bool get isMonthly => targetType == 'monthly';

  factory AdmissionTargetRecord.fromJson(Map<String, dynamic> json) {
    final splits = json['weekly_splits'];
    return AdmissionTargetRecord(
      id: _asInt(json['id']),
      targetType: json['target_type']?.toString() ?? '',
      targetTypeLabel:
          json['target_type_label']?.toString() ??
          json['target_type']?.toString() ??
          'Target',
      periodStart: json['period_start']?.toString(),
      periodEnd: json['period_end']?.toString(),
      targetCount: _asInt(json['target_count']),
      achieved: _asInt(json['achieved']),
      remaining: _asInt(json['remaining']),
      percentage: _asDouble(json['percentage']),
      weeklySplits: splits is List
          ? splits
                .whereType<Map>()
                .map(
                  (item) => AdmissionTargetSplit.fromJson(
                    Map<String, dynamic>.from(item),
                  ),
                )
                .toList()
          : const [],
    );
  }
}

int _asInt(Object? value) {
  if (value is int) return value;
  if (value is num) return value.toInt();
  return int.tryParse('$value') ?? 0;
}

double _asDouble(Object? value) {
  if (value is double) return value;
  if (value is num) return value.toDouble();
  return double.tryParse('$value') ?? 0;
}
