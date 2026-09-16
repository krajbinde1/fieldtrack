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
    this.initialStatus = 'submitted',
  });

  final AuthController auth;
  final String apiPrefix;
  final String initialStatus;

  @override
  State<SupervisorAdmissionListScreen> createState() =>
      _SupervisorAdmissionListScreenState();
}

class _SupervisorAdmissionListScreenState
    extends State<SupervisorAdmissionListScreen> {
  static const _filters = <(String, String, Color)>[
    ('Submitted', 'submitted', AppColors.info),
    ('Confirmed', 'confirmed', AppColors.success),
    ('Draft', 'draft', AppColors.warning),
    ('Reverted', 'reverted', AppColors.accent),
    ('Rejected', 'rejected', AppColors.error),
    ('Total', '', AppColors.primary),
  ];

  AdmissionApi? _api;
  late String _status;
  late Future<({Map<String, int> counts, List<AdmissionRecord> items})> _future;

  @override
  void initState() {
    super.initState();
    _status = _normalize(widget.initialStatus);
    _future = _load();
  }

  @override
  void didUpdateWidget(covariant SupervisorAdmissionListScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    final next = _normalize(widget.initialStatus);
    if (oldWidget.initialStatus != widget.initialStatus && next != _status) {
      _status = next;
      _future = _load();
    }
  }

  String _normalize(String status) {
    if (status == 'total' || status == 'all') return '';
    return status;
  }

  Future<({Map<String, int> counts, List<AdmissionRecord> items})> _load() async {
    _api ??= await AdmissionApi.create();
    final counts = await _api!.supervisorSummary(widget.apiPrefix);
    final items = await _api!.supervisorList(
      widget.apiPrefix,
      status: _status.isEmpty ? null : _status,
    );
    return (counts: counts, items: items);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  void _select(String status) {
    final next = _normalize(status);
    if (_status == next) return;
    setState(() {
      _status = next;
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
              'total': 0,
            };
            return ListView(
              padding: const EdgeInsets.all(AppSpacing.screenPadding),
              children: [
                _StatusCountGrid(
                  counts: counts,
                  selected: _status,
                  filters: _filters,
                  onSelect: _select,
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
                else if ((snapshot.data?.items ?? const <AdmissionRecord>[])
                    .isEmpty)
                  const Padding(
                    padding: EdgeInsets.only(top: 32),
                    child: PgEmptyState(
                      icon: Icon(Icons.how_to_reg_rounded),
                      message:
                          'No admissions in this filter for your assigned center(s).',
                    ),
                  )
                else
                  ...[
                    for (final item in snapshot.data!.items) ...[
                      PgCard(
                        onTap: () async {
                          await context.push('$prefix/admissions/${item.id}');
                          if (mounted) await _refresh();
                        },
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
                                    style: Theme.of(context)
                                        .textTheme
                                        .titleMedium,
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
                              ]
                                  .where((part) => (part ?? '').trim().isNotEmpty)
                                  .join(' · '),
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

class _StatusCountGrid extends StatelessWidget {
  const _StatusCountGrid({
    required this.counts,
    required this.selected,
    required this.filters,
    required this.onSelect,
  });

  final Map<String, int> counts;
  final String selected;
  final List<(String, String, Color)> filters;
  final ValueChanged<String> onSelect;

  @override
  Widget build(BuildContext context) {
    return LayoutBuilder(
      builder: (context, constraints) {
        final columns = constraints.maxWidth >= 420 ? 3 : 2;
        final gap = 8.0;
        final width =
            (constraints.maxWidth - gap * (columns - 1)) / columns;

        return Wrap(
          spacing: gap,
          runSpacing: gap,
          children: [
            for (final filter in filters)
              SizedBox(
                width: width,
                child: _StatusCountCard(
                  label: filter.$1,
                  value: '${counts[filter.$2.isEmpty ? 'total' : filter.$2] ?? 0}',
                  selected: selected == filter.$2,
                  color: filter.$3,
                  onTap: () => onSelect(filter.$2),
                ),
              ),
          ],
        );
      },
    );
  }
}

class _StatusCountCard extends StatelessWidget {
  const _StatusCountCard({
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
      color: selected ? color.withValues(alpha: 0.16) : Colors.white,
      elevation: 0,
      borderRadius: BorderRadius.circular(16),
      child: InkWell(
        onTap: onTap,
        borderRadius: BorderRadius.circular(16),
        child: Ink(
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(16),
            border: Border.all(
              color: selected ? color : AppColors.border.withValues(alpha: 0.8),
              width: selected ? 1.4 : 1,
            ),
            boxShadow: const [
              BoxShadow(
                color: AppColors.shadow,
                blurRadius: 12,
                offset: Offset(0, 3),
              ),
            ],
          ),
          padding: const EdgeInsets.fromLTRB(12, 10, 12, 10),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                value,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.titleLarge?.copyWith(
                      fontWeight: FontWeight.w800,
                      height: 1.1,
                      color: color,
                    ),
              ),
              const SizedBox(height: 4),
              Text(
                label,
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
                style: Theme.of(context).textTheme.labelSmall?.copyWith(
                      fontWeight: FontWeight.w700,
                      color: AppColors.textSecondary,
                    ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
