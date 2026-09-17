import 'package:flutter/material.dart';
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

class FieldActivityDetailScreen extends StatefulWidget {
  const FieldActivityDetailScreen({
    super.key,
    required this.activityId,
    this.apiPrefix,
  });

  final int activityId;
  final String? apiPrefix;

  @override
  State<FieldActivityDetailScreen> createState() =>
      _FieldActivityDetailScreenState();
}

class _FieldActivityDetailScreenState extends State<FieldActivityDetailScreen> {
  late Future<FieldActivity> _future;

  @override
  void initState() {
    super.initState();
    _future = _load();
  }

  Future<FieldActivity> _load() async {
    final api = await FieldActivityApi.create();
    if (widget.apiPrefix == null || widget.apiPrefix!.isEmpty) {
      return api.show(widget.activityId);
    }
    return api.supervisorShow(widget.apiPrefix!, widget.activityId);
  }

  Future<void> _refresh() async {
    setState(() => _future = _load());
    await _future;
  }

  @override
  Widget build(BuildContext context) {
    return PgPageScaffold(
      title: 'Activity Details',
      showBack: true,
      body: RefreshIndicator(
        onRefresh: _refresh,
        child: FutureBuilder<FieldActivity>(
          future: _future,
          builder: (context, snapshot) {
            if (snapshot.connectionState != ConnectionState.done) {
              return const PgLoadingState();
            }
            if (snapshot.hasError) {
              return PgErrorState(message: '${snapshot.error}', onRetry: _refresh);
            }
            final activity = snapshot.data;
            if (activity == null) {
              return const PgEmptyState(message: 'Activity not found.');
            }
            return ListView(
              padding: const EdgeInsets.all(AppSpacing.screenPadding),
              children: [
                PgCard(
                  child: Column(
                    children: [
                      if ((activity.employeeName ?? '').isNotEmpty)
                        _Row(label: 'Employee', value: activity.employeeName!),
                      _Row(label: 'Activity', value: activity.displayName),
                      _Row(label: 'Type', value: activity.activityTypeLabel ?? activity.activityType),
                      _Row(label: 'Date & Time', value: _when(activity)),
                      if ((activity.centerName ?? '').isNotEmpty)
                        _Row(label: 'Center', value: activity.centerName!),
                      if ((activity.remarks ?? '').isNotEmpty)
                        _Row(label: 'Remarks', value: activity.remarks!),
                    ],
                  ),
                ),
                const SizedBox(height: AppSpacing.md),
                PgCard(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        'Location',
                        style: Theme.of(context).textTheme.titleSmall?.copyWith(
                          fontWeight: FontWeight.w800,
                        ),
                      ),
                      const SizedBox(height: AppSpacing.sm),
                      InkWell(
                        onTap: () => openCapturedMapsLocation(
                          context,
                          mapsUrl: activity.mapsUrl,
                          latitude: activity.latitude,
                          longitude: activity.longitude,
                        ),
                        child: Row(
                          children: [
                            const Icon(Icons.place_outlined, color: AppColors.primary),
                            const SizedBox(width: 8),
                            Expanded(
                              child: Text(
                                activity.location ?? 'Location captured',
                                style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                                  color: AppColors.primary,
                                  decoration: TextDecoration.underline,
                                ),
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (activity.latitude != null && activity.longitude != null) ...[
                        const SizedBox(height: 6),
                        Text(
                          'Lat ${activity.latitude!.toStringAsFixed(6)}, Lng ${activity.longitude!.toStringAsFixed(6)}',
                          style: Theme.of(context).textTheme.bodySmall,
                        ),
                      ],
                    ],
                  ),
                ),
                if ((activity.photoUrl ?? '').isNotEmpty) ...[
                  const SizedBox(height: AppSpacing.md),
                  PgCard(
                    child: PgProofImage(url: activity.photoUrl, label: 'Photo'),
                  ),
                ],
              ],
            );
          },
        ),
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

class _Row extends StatelessWidget {
  const _Row({required this.label, required this.value});

  final String label;
  final String value;

  @override
  Widget build(BuildContext context) {
    return Padding(
      padding: const EdgeInsets.only(bottom: AppSpacing.sm),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 110,
            child: Text(
              label,
              style: Theme.of(context).textTheme.bodySmall?.copyWith(
                color: AppColors.textSecondary,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: Theme.of(context).textTheme.bodyMedium?.copyWith(
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
    );
  }
}
