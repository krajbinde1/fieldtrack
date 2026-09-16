import 'package:flutter/material.dart';
import 'package:intl/intl.dart';

import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_progress_bar.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../../core/widgets/design/pg_status_badge.dart';
import '../api/admission_api.dart';
import '../models/admission_target.dart';

class EmployeeTargetsScreen extends StatefulWidget {
  const EmployeeTargetsScreen({super.key});

  @override
  State<EmployeeTargetsScreen> createState() => _EmployeeTargetsScreenState();
}

class _EmployeeTargetsScreenState extends State<EmployeeTargetsScreen> {
  late Future<List<AdmissionTargetRecord>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<AdmissionTargetRecord>> _load() async {
    final api = await AdmissionApi.create();
    return api.targets();
  }

  Future<void> _refresh() async {
    final next = _load();
    setState(() => _future = next);
    await next;
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: 'My Targets',
      showBack: true,
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder<List<AdmissionTargetRecord>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const PgLoadingState();
            }
            if (snapshot.hasError) {
              return PgErrorState(
                message: '${snapshot.error}',
                onRetry: _refresh,
              );
            }
            final items = snapshot.data ?? const <AdmissionTargetRecord>[];
            if (items.isEmpty) {
              return ListView(
                children: const [
                  SizedBox(height: 80),
                  PgEmptyState(
                    icon: Icon(Icons.flag_outlined),
                    message: 'No admission targets assigned yet.',
                  ),
                ],
              );
            }

            final monthly = items.where((item) => item.isMonthly).toList();
            final weekly = items.where((item) => !item.isMonthly).toList();

            return ListView(
              padding: const EdgeInsets.all(AppSpacing.screenPadding),
              children: [
                if (monthly.isNotEmpty) ...[
                  _SectionLabel('Monthly targets'),
                  const SizedBox(height: AppSpacing.sm),
                  for (final item in monthly) ...[
                    _TargetHistoryCard(item: item),
                    const SizedBox(height: AppSpacing.sm),
                  ],
                ],
                if (weekly.isNotEmpty) ...[
                  if (monthly.isNotEmpty) const SizedBox(height: AppSpacing.sm),
                  _SectionLabel('Weekly targets'),
                  const SizedBox(height: AppSpacing.sm),
                  for (final item in weekly) ...[
                    _TargetHistoryCard(item: item),
                    const SizedBox(height: AppSpacing.sm),
                  ],
                ],
              ],
            );
          },
        ),
      ),
    );
  }
}

class _SectionLabel extends StatelessWidget {
  const _SectionLabel(this.label);

  final String label;

  @override
  Widget build(BuildContext context) {
    return Text(
      label,
      style: Theme.of(context).textTheme.titleSmall?.copyWith(
            fontWeight: FontWeight.w800,
          ),
    );
  }
}

class _TargetHistoryCard extends StatelessWidget {
  const _TargetHistoryCard({required this.item});

  final AdmissionTargetRecord item;

  @override
  Widget build(BuildContext context) {
    return PgCard(
      padding: const EdgeInsets.all(AppSpacing.md),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  _range(item.periodStart, item.periodEnd),
                  style: Theme.of(context).textTheme.titleSmall?.copyWith(
                        fontWeight: FontWeight.w700,
                      ),
                ),
              ),
              PgStatusBadge(
                label: item.targetTypeLabel,
                tone: item.isMonthly ? PgStatusTone.info : PgStatusTone.approved,
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          Row(
            children: [
              _MiniStat(label: 'Target', value: '${item.targetCount}'),
              _MiniStat(label: 'Achieved', value: '${item.achieved}'),
              _MiniStat(label: 'Remaining', value: '${item.remaining}'),
              _MiniStat(
                label: 'Achievement',
                value: _percent(item.percentage, item.targetCount),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.sm),
          PgProgressBar(
            label: 'Achievement',
            percentage: item.targetCount > 0 ? item.percentage : null,
            currentLabel: '${item.achieved}',
            targetLabel: '${item.targetCount}',
          ),
          if (item.weeklySplits.isNotEmpty) ...[
            const SizedBox(height: AppSpacing.md),
            Text(
              'Weekly splits',
              style: Theme.of(context).textTheme.labelMedium?.copyWith(
                    fontWeight: FontWeight.w700,
                  ),
            ),
            const SizedBox(height: AppSpacing.sm),
            for (final split in item.weeklySplits)
              Padding(
                padding: const EdgeInsets.only(bottom: 8),
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(10),
                  decoration: BoxDecoration(
                    color: AppColors.primary.withValues(alpha: 0.04),
                    borderRadius: BorderRadius.circular(12),
                    border: Border.all(
                      color: AppColors.border.withValues(alpha: 0.8),
                    ),
                  ),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        _range(split.periodStart, split.periodEnd),
                        style: Theme.of(context).textTheme.labelMedium,
                      ),
                      const SizedBox(height: 4),
                      Text(
                        'Target ${split.targetCount}  ·  Achieved ${split.achieved}  ·  ${_percent(split.percentage, split.targetCount)}',
                        style: Theme.of(context).textTheme.bodySmall,
                      ),
                    ],
                  ),
                ),
              ),
          ],
        ],
      ),
    );
  }

  static String _range(String? start, String? end) {
    final left = _fmt(start);
    final right = _fmt(end);
    if (left == '—' && right == '—') return 'Target period';
    return '$left → $right';
  }

  static String _fmt(String? value) {
    if (value == null || value.isEmpty) return '—';
    final parsed = DateTime.tryParse(value);
    if (parsed == null) return value;
    return DateFormat('d MMM yyyy').format(parsed);
  }

  static String _percent(double percentage, int target) {
    if (target <= 0) return 'N/A';
    if (percentage == percentage.roundToDouble()) {
      return '${percentage.toInt()}%';
    }
    return '${percentage.toStringAsFixed(1)}%';
  }
}

class _MiniStat extends StatelessWidget {
  const _MiniStat({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Expanded(
      child: Column(
        children: [
          Text(
            value,
            style: Theme.of(context).textTheme.titleSmall?.copyWith(
                  fontWeight: FontWeight.w800,
                ),
          ),
          Text(
            label,
            textAlign: TextAlign.center,
            style: Theme.of(context).textTheme.labelSmall?.copyWith(fontSize: 10),
          ),
        ],
      ),
    );
  }
}
