import 'package:flutter/material.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/utils/open_maps_location.dart';
import '../../../core/widgets/design/pg_card.dart';
import '../../../core/widgets/design/pg_empty_state.dart';
import '../../../core/widgets/design/pg_proof_image.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../api/field_activity_api.dart';
import '../models/field_activity.dart';

class FieldActivityListScreen extends StatefulWidget {
  const FieldActivityListScreen({super.key});

  @override
  State<FieldActivityListScreen> createState() => _FieldActivityListScreenState();
}

class _FieldActivityListScreenState extends State<FieldActivityListScreen> {
  late Future<List<FieldActivity>> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<List<FieldActivity>> _load() async {
    return (await FieldActivityApi.create()).mine();
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: 'My Activities',
      showBack: true,
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder<List<FieldActivity>>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const PgLoadingState();
            }
            if (snapshot.hasError) {
              return PgErrorState(message: '${snapshot.error}', onRetry: _refresh);
            }
            final items = snapshot.data ?? const <FieldActivity>[];
            if (items.isEmpty) {
              return ListView(
                children: [
                  const SizedBox(height: 80),
                  PgEmptyState(
                    icon: const Icon(Icons.photo_camera_outlined),
                    message: 'You have not submitted any field activities yet.',
                    actionLabel: 'Add Activity',
                    onAction: () => context.push('/field-activities/new'),
                  ),
                ],
              );
            }
            return ListView.separated(
              padding: const EdgeInsets.all(AppSpacing.screenPadding),
              itemCount: items.length,
              separatorBuilder: (_, _) => const SizedBox(height: AppSpacing.md),
              itemBuilder: (context, index) {
                return FieldActivityCard(
                  activity: items[index],
                  onOpen: () => context.push('/field-activities/${items[index].id}'),
                );
              },
            );
          },
        ),
      ),
    );
  }
}

class FieldActivityCard extends StatelessWidget {
  const FieldActivityCard({
    super.key,
    required this.activity,
    required this.onOpen,
    this.showEmployee = false,
  });

  final FieldActivity activity;
  final VoidCallback onOpen;
  final bool showEmployee;

  @override
  Widget build(BuildContext context) {
    return PgCard(
      onTap: onOpen,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          if (showEmployee && (activity.employeeName ?? '').isNotEmpty)
            Text(
              activity.employeeName!,
              style: Theme.of(context).textTheme.titleMedium,
            ),
          Text(
            activity.displayName,
            style: showEmployee
                ? Theme.of(context).textTheme.bodyMedium
                : Theme.of(context).textTheme.titleMedium,
          ),
          const SizedBox(height: 4),
          Text(
            _when(activity),
            style: Theme.of(context).textTheme.bodySmall,
          ),
          if ((activity.location ?? '').isNotEmpty) ...[
            const SizedBox(height: 6),
            InkWell(
              onTap: () => openCapturedMapsLocation(
                context,
                mapsUrl: activity.mapsUrl,
                latitude: activity.latitude,
                longitude: activity.longitude,
              ),
              child: Row(
                children: [
                  const Icon(Icons.place_outlined, size: 16, color: AppColors.primary),
                  const SizedBox(width: 4),
                  Expanded(
                    child: Text(
                      activity.location!,
                      style: Theme.of(context).textTheme.bodySmall?.copyWith(
                        color: AppColors.primary,
                        decoration: TextDecoration.underline,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
          if ((activity.photoUrl ?? '').isNotEmpty) ...[
            const SizedBox(height: AppSpacing.sm),
            PgProofImage(url: activity.photoUrl, label: 'Photo', height: 140),
          ],
          const SizedBox(height: 8),
          Align(
            alignment: Alignment.centerRight,
            child: Text(
              'View Details',
              style: Theme.of(context).textTheme.labelLarge?.copyWith(
                color: AppColors.primary,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }

  String _when(FieldActivity activity) {
    if (activity.activityAt != null) {
      return DateFormat('d MMM yyyy, hh:mm a').format(activity.activityAt!);
    }
    return activity.whenLabel;
  }
}
