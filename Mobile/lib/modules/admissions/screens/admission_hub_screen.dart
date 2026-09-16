import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';

import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_scaffold.dart';

class AdmissionHubScreen extends StatelessWidget {
  const AdmissionHubScreen({super.key});

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: 'Admission',
      showBack: true,
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.screenPadding),
        children: [
          Text(
            'Capture and track field admissions',
            style: Theme.of(context).textTheme.bodyMedium?.copyWith(
              color: AppColors.textSecondary,
            ),
          ),
          const SizedBox(height: AppSpacing.lg),
          _HubTile(
            icon: Icons.add_circle_outline_rounded,
            title: 'New Admission',
            subtitle: 'Start a 5-step admission form',
            color: AppColors.primary,
            onTap: () => context.push('/admissions/new'),
          ),
          const SizedBox(height: AppSpacing.md),
          _HubTile(
            icon: Icons.drafts_outlined,
            title: 'Draft Admissions',
            subtitle: 'Continue incomplete applications',
            color: AppColors.warning,
            onTap: () => context.push('/admissions/drafts'),
          ),
          const SizedBox(height: AppSpacing.md),
          _HubTile(
            icon: Icons.task_alt_rounded,
            title: 'Submitted Admissions',
            subtitle: 'View completed applications',
            color: AppColors.success,
            onTap: () => context.push('/admissions/submitted'),
          ),
        ],
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

  final IconData icon;
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
            child: Icon(icon, color: color),
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
