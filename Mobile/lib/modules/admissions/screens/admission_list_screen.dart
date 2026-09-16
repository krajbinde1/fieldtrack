import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../../core/widgets/design/pg_status_badge.dart';
import '../api/admission_api.dart';
import '../models/admission.dart';
import '../widgets/admission_status_count_cards.dart';

class AdmissionListScreen extends StatefulWidget {
  const AdmissionListScreen({super.key, this.initialStatus = ''});

  final String initialStatus;

  @override
  State<AdmissionListScreen> createState() => _AdmissionListScreenState();
}

class _AdmissionListScreenState extends State<AdmissionListScreen> {
  static const _filters = <(String, String, Color)>[
    ('Total', '', AppColors.primary),
    ('Draft', 'draft', AppColors.warning),
    ('Submitted', 'submitted', AppColors.info),
    ('Confirmed', 'confirmed', AppColors.success),
    ('Reverted', 'reverted', AppColors.accent),
    ('Rejected', 'rejected', AppColors.error),
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
  void didUpdateWidget(covariant AdmissionListScreen oldWidget) {
    super.didUpdateWidget(oldWidget);
    final next = _normalize(widget.initialStatus);
    if (oldWidget.initialStatus != widget.initialStatus && next != _status) {
      _status = next;
      _future = _load();
    }
  }

  String _normalize(String status) {
    if (status == 'total' || status == 'all' || status == 'drafts') {
      return status == 'drafts' ? 'draft' : '';
    }
    return status;
  }

  Future<({Map<String, int> counts, List<AdmissionRecord> items})> _load()
      async {
    _api ??= await AdmissionApi.create();
    final counts = await _api!.mySummary();
    final items = await _itemsFor(_status);
    return (counts: counts, items: items);
  }

  Future<List<AdmissionRecord>> _itemsFor(String status) async {
    Future<List<AdmissionRecord>> drafts() => _api!.drafts();
    Future<List<AdmissionRecord>> submitted() => _api!.submitted();

    switch (status) {
      case 'draft':
        return (await drafts()).where((item) => item.isDraft).toList();
      case 'reverted':
        return (await drafts()).where((item) => item.isReverted).toList();
      case 'submitted':
        return (await submitted()).where((item) => item.isSubmitted).toList();
      case 'confirmed':
        return (await submitted()).where((item) => item.isConfirmed).toList();
      case 'rejected':
        return (await submitted()).where((item) => item.isRejected).toList();
      default:
        final all = <AdmissionRecord>[
          ...await drafts(),
          ...await submitted(),
        ];
        all.sort((a, b) => (b.updatedAt ?? '').compareTo(a.updatedAt ?? ''));
        return all;
    }
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

  String get _title => switch (_status) {
        'draft' => 'Draft Admissions',
        'submitted' => 'Submitted Admissions',
        'confirmed' => 'Confirmed Admissions',
        'reverted' => 'Reverted Admissions',
        'rejected' => 'Rejected Admissions',
        _ => 'My Admissions',
      };

  Future<void> _delete(AdmissionRecord record) async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Delete draft?'),
        content: Text(
          'This will remove the draft for ${record.displayName.isEmpty ? 'this applicant' : record.displayName}.',
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('Cancel'),
          ),
          FilledButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Delete'),
          ),
        ],
      ),
    );
    if (confirmed != true) return;
    try {
      await _api!.deleteDraft(record.id);
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Draft deleted.')),
      );
      await _refresh();
    } catch (error) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text('$error')),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: _title,
      showBack: true,
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder(
          future: _future,
          builder: (context, snapshot) {
            final counts = snapshot.data?.counts ?? const {
              'total': 0,
              'draft': 0,
              'submitted': 0,
              'confirmed': 0,
              'reverted': 0,
              'rejected': 0,
            };
            return ListView(
              padding: const EdgeInsets.all(AppSpacing.screenPadding),
              children: [
                AdmissionStatusCountCards(
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
                  Padding(
                    padding: const EdgeInsets.only(top: 32),
                    child: PgEmptyState(
                      icon: Icon(
                        _status == 'draft'
                            ? Icons.drafts_outlined
                            : Icons.task_alt_rounded,
                      ),
                      message: 'No admissions in this filter.',
                      actionLabel: 'New Admission',
                      onAction: () async {
                        await context.push('/admissions/new');
                        if (mounted) await _refresh();
                      },
                    ),
                  )
                else
                  ...[
                    for (final item in snapshot.data!.items) ...[
                      PgCard(
                        onTap: () async {
                          await context.push('/admissions/${item.id}');
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
                                        ? 'Untitled admission'
                                        : item.displayName,
                                    style:
                                        Theme.of(context).textTheme.titleMedium,
                                  ),
                                ),
                                PgStatusBadge(
                                  label: item.statusLabel,
                                  tone: item.statusTone,
                                ),
                              ],
                            ),
                            const SizedBox(height: 6),
                            Text(
                              item.schemeName ?? 'Scheme not selected',
                              style: Theme.of(context).textTheme.bodySmall,
                            ),
                            if ((item.reviewReason ?? '').trim().isNotEmpty) ...[
                              const SizedBox(height: 6),
                              Text(
                                item.isRejected
                                    ? 'Rejected: ${item.reviewReason}'
                                    : 'Reverted: ${item.reviewReason}',
                                style: Theme.of(context)
                                    .textTheme
                                    .bodySmall
                                    ?.copyWith(
                                      color: item.isRejected
                                          ? AppColors.error
                                          : AppColors.warning,
                                    ),
                              ),
                            ],
                            const SizedBox(height: 8),
                            Row(
                              children: [
                                Text(
                                  _dateLabel(item),
                                  style: Theme.of(context)
                                      .textTheme
                                      .labelSmall
                                      ?.copyWith(color: AppColors.textMuted),
                                ),
                                const Spacer(),
                                if (item.isDraft)
                                  IconButton(
                                    onPressed: () => _delete(item),
                                    icon: const Icon(
                                      Icons.delete_outline_rounded,
                                    ),
                                    color: AppColors.error,
                                  ),
                              ],
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

  String _dateLabel(AdmissionRecord item) {
    final raw = item.submittedAt ?? item.updatedAt;
    if (raw == null || raw.isEmpty) return '';
    final parsed = DateTime.tryParse(raw);
    if (parsed == null) return raw;
    return DateFormat('d MMM yyyy, h:mm a').format(parsed.toLocal());
  }
}
