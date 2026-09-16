import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../../core/widgets/design/pg_status_badge.dart';
import '../../auth/providers/auth_controller.dart';
import '../api/admission_api.dart';
import '../models/admission.dart';

class SupervisorAdmissionListScreen extends StatefulWidget {
  const SupervisorAdmissionListScreen({
    super.key,
    required this.auth,
    required this.apiPrefix,
  });

  final AuthController auth;
  final String apiPrefix;

  @override
  State<SupervisorAdmissionListScreen> createState() =>
      _SupervisorAdmissionListScreenState();
}

class _SupervisorAdmissionListScreenState
    extends State<SupervisorAdmissionListScreen> {
  static const _filters = <(String, String)>[
    ('Submitted', 'submitted'),
    ('Confirmed', 'confirmed'),
    ('Draft', 'draft'),
    ('Reverted', 'reverted'),
    ('Rejected', 'rejected'),
  ];

  AdmissionApi? _api;
  String _status = 'submitted';
  late Future<({Map<String, int> counts, List<AdmissionRecord> items})> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<({Map<String, int> counts, List<AdmissionRecord> items})> _load() async {
    _api ??= await AdmissionApi.create();
    final counts = await _api!.supervisorSummary(widget.apiPrefix);
    final items = await _api!.supervisorList(widget.apiPrefix, status: _status);
    return (counts: counts, items: items);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  void _select(String status) {
    if (_status == status) return;
    setState(() {
      _status = status;
      _future = _load();
    });
  }

  @override
  Widget build(BuildContext context) {
    final prefix = widget.apiPrefix == 'director' ? '/director' : '/manager';
    return PgPageScaffold(
      title: 'Admissions',
      showBack: true,
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder(
          future: _future,
          builder: (context, snapshot) {
            final counts = snapshot.data?.counts ?? const {
              'submitted': 0,
              'confirmed': 0,
              'draft': 0,
              'reverted': 0,
              'rejected': 0,
            };
            return ListView(
              padding: const EdgeInsets.all(AppSpacing.screenPadding),
              children: [
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  children: [
                    _SummaryChip(
                      label: 'Total Submitted',
                      value: '${counts['submitted'] ?? 0}',
                      selected: _status == 'submitted',
                      color: AppColors.info,
                      onTap: () => _select('submitted'),
                    ),
                    _SummaryChip(
                      label: 'Confirmed',
                      value: '${counts['confirmed'] ?? 0}',
                      selected: _status == 'confirmed',
                      color: AppColors.success,
                      onTap: () => _select('confirmed'),
                    ),
                    _SummaryChip(
                      label: 'Draft',
                      value: '${counts['draft'] ?? 0}',
                      selected: _status == 'draft',
                      color: AppColors.warning,
                      onTap: () => _select('draft'),
                    ),
                    _SummaryChip(
                      label: 'Reverted',
                      value: '${counts['reverted'] ?? 0}',
                      selected: _status == 'reverted',
                      color: AppColors.accent,
                      onTap: () => _select('reverted'),
                    ),
                    _SummaryChip(
                      label: 'Rejected',
                      value: '${counts['rejected'] ?? 0}',
                      selected: _status == 'rejected',
                      color: AppColors.error,
                      onTap: () => _select('rejected'),
                    ),
                  ],
                ),
                const SizedBox(height: AppSpacing.md),
                SingleChildScrollView(
                  scrollDirection: Axis.horizontal,
                  child: Row(
                    children: [
                      for (final filter in _filters) ...[
                        ChoiceChip(
                          label: Text(filter.$1),
                          selected: _status == filter.$2,
                          onSelected: (_) => _select(filter.$2),
                        ),
                        const SizedBox(width: 8),
                      ],
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                if (snapshot.connectionState != ConnectionState.done)
                  const Padding(
                    padding: EdgeInsets.only(top: 48),
                    child: Center(child: CircularProgressIndicator()),
                  )
                else if (snapshot.hasError)
                  PgErrorState(
                    message: '${snapshot.error}',
                    onRetry: _refresh,
                  )
                else if ((snapshot.data?.items ?? const <AdmissionRecord>[]).isEmpty)
                  const Padding(
                    padding: EdgeInsets.only(top: 32),
                    child: PgEmptyState(
                      icon: Icon(Icons.how_to_reg_rounded),
                      message: 'No admissions in this filter for your assigned center(s).',
                    ),
                  )
                else
                  ...[
                    for (final item in snapshot.data!.items) ...[
                      PgCard(
                        onTap: () => context.push('$prefix/admissions/${item.id}'),
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Row(
                              children: [
                                Expanded(
                                  child: Text(
                                    item.displayName.isEmpty
                                        ? 'Admission #${item.id}'
                                        : item.displayName,
                                    style: Theme.of(context).textTheme.titleMedium,
                                  ),
                                ),
                                PgStatusBadge(
                                  label: item.statusLabel,
                                  tone: item.statusTone,
                                ),
                              ],
                            ),
                            const SizedBox(height: 4),
                            Text(
                              [
                                item.employeeName,
                                item.centerName,
                                item.schemeName ?? item.projectName,
                              ].where((part) => (part ?? '').trim().isNotEmpty).join(' · '),
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                            if ((item.reviewReason ?? '').trim().isNotEmpty)
                              Padding(
                                padding: const EdgeInsets.only(top: 4),
                                child: Text(
                                  item.reviewReason!,
                                  style: Theme.of(context).textTheme.bodySmall,
                                ),
                              ),
                            if (item.updatedAt != null)
                              Text(
                                _fmt(item.updatedAt!),
                                style: Theme.of(context).textTheme.bodySmall,
                              ),
                          ],
                        ),
                      ),
                      const SizedBox(height: AppSpacing.md),
                    ],
                  ],
              ],
            );
          },
        ),
      ),
    );
  }

  String _fmt(String raw) {
    final parsed = DateTime.tryParse(raw);
    if (parsed == null) return raw;
    return DateFormat('d MMM yyyy').format(parsed);
  }
}

class _SummaryChip extends StatelessWidget {
  const _SummaryChip({
    required this.label,
    required this.value,
    required this.selected,
    required this.color,
    required this.onTap,
  });

  final String label;
  final String value;
  final bool selected;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return Material(
      color: selected ? color.withValues(alpha: 0.16) : color.withValues(alpha: 0.08),
      borderRadius: BorderRadius.circular(14),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(14),
        child: Container(
          width: 104,
          padding: const EdgeInsets.fromLTRB(10, 10, 10, 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(14),
            border: Border.all(
              color: selected ? color : color.withValues(alpha: 0.18),
            ),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                value,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                      color: color,
                    ),
              ),
              Text(
                label,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                      height: 1.15,
                    ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
