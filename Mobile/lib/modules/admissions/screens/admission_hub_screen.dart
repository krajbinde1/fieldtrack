import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../api/admission_api.dart';
import '../widgets/admission_status_count_cards.dart';

class AdmissionHubScreen extends StatefulWidget {
  const AdmissionHubScreen({super.key});

  @override
  State<AdmissionHubScreen> createState() => _AdmissionHubScreenState();
}

class _AdmissionHubScreenState extends State<AdmissionHubScreen> {
  static const _filters = <(String, String, Color)>[
    ('Total', '', AppColors.primary),
    ('Draft', 'draft', AppColors.warning),
    ('Submitted', 'submitted', AppColors.info),
    ('Confirmed', 'confirmed', AppColors.success),
    ('Reverted', 'reverted', AppColors.accent),
    ('Rejected', 'rejected', AppColors.error),
  ];

  AdmissionApi? _api;
  late Future<Map<String, int>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<Map<String, int>> _load() async {
    _api ??= await AdmissionApi.create();
    return _api!.mySummary();
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  Future<void> _open(String path) async {
    await context.push(path);
    if (mounted) await _refresh();
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: 'Admission',
      showBack: true,
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: ListView(
          padding: const EdgeInsets.all(AppSpacing.screenPadding),
          children: [
            Text(
              'Your admission counts',
              style: Theme.of(context).textTheme.titleMedium?.copyWith(
                    fontWeight: FontWeight.w800,
                  ),
            ),
            const SizedBox(height: AppSpacing.sm),
            FutureBuilder<Map<String, int>>(
              future: _future,
              builder: (context, snapshot) {
                if (snapshot.hasError) {
                  return Text(
                    '${snapshot.error}',
                    style: Theme.of(context).textTheme.bodySmall?.copyWith(
                          color: AppColors.error,
                        ),
                  );
                }
                return AdmissionStatusCountCards(
                  counts: snapshot.data ?? const {
                    'total': 0,
                    'draft': 0,
                    'submitted': 0,
                    'confirmed': 0,
                    'reverted': 0,
                    'rejected': 0,
                  },
                  filters: _filters,
                  onSelect: (status) => _open(
                    status.isEmpty
                        ? '/admissions/list'
                        : '/admissions/list?status=$status',
                  ),
                );
              },
            ),
            const SizedBox(height: AppSpacing.lg),
            _HubTile(
              icon: const Icon(Icons.add_circle_outline),
              title: 'New Admission',
              subtitle: 'Start a 5-step admission form',
              color: AppColors.primary,
              onTap: () => _open('/admissions/new'),
            ),
          ],
        ),
      ),
    );
  }
}

class _HubTile extends StatelessWidget {
  const _HubTile({
    required this.icon,
    required this.title,
    required this.subtitle,
    required this.color,
    required this.onTap,
  });

  final Widget icon;
  final String title;
  final String subtitle;
  final Color color;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return PgCard(
      onTap: onTap,
      child: Row(
        children: [
          Container(
            width: 48,
            height: 48,
            decoration: BoxDecoration(
              color: color.withValues(alpha: 0.12),
              borderRadius: BorderRadius.circular(14),
            ),
            child: IconTheme(
              data: IconThemeData(color: color, size: 24),
              child: icon,
            ),
          ),
          const SizedBox(width: AppSpacing.md),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(title, style: Theme.of(context).textTheme.titleMedium),
                const SizedBox(height: 2),
                Text(
                  subtitle,
                  style: Theme.of(context).textTheme.bodySmall,
                ),
              ],
            ),
          ),
          const Icon(Icons.chevron_right_rounded, color: AppColors.textMuted),
        ],
      ),
    );
  }
}
