import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';
import 'package:intl/intl.dart';

import '../../../core/design/app_colors.dart';
import '../../../core/design/app_spacing.dart';
import '../../../core/widgets/design/pg_quick_action.dart';
import '../../../core/widgets/design/pg_scaffold.dart';
import '../../../core/widgets/design/pg_welcome_card.dart';
import '../../attendance/providers/attendance_provider.dart';
import '../../auth/providers/auth_controller.dart';

class EmployeeDashboardScreen extends ConsumerWidget {
  const EmployeeDashboardScreen({super.key, required this.auth});
  final AuthController auth;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final name = auth.session?.employee.fullName ??
        auth.session?.user.loginId ??
        'Employee';

    return PgPageScaffold(
      title: 'Param FieldTrack',
      auth: auth,
      body: ListView(
        padding: const EdgeInsets.all(AppSpacing.md),
        children: [
          PgWelcomeCard(
            name: name,
            dateLabel: DateFormat('EEEE, d MMM yyyy').format(DateTime.now()),
            photoUrl: auth.session?.employee.profilePhotoUrl,
            role: auth.userRole.label,
          ),
          const SizedBox(height: AppSpacing.lg),
          Row(
            children: [
              Expanded(
                child: PgQuickAction(
                  icon: const Icon(Icons.fingerprint_rounded),
                  label: 'Attendance',
                  color: AppColors.primary,
                  onTap: () async {
                    ref.invalidate(todayAttendanceProvider);
                    await context.push('/attendance');
                  },
                ),
              ),
              Expanded(
                child: PgQuickAction(
                  icon: const Icon(Icons.history_rounded),
                  label: 'History',
                  color: AppColors.secondary,
                  onTap: () => context.push('/attendance/history'),
                ),
              ),
            ],
          ),
          const SizedBox(height: AppSpacing.lg),
          Row(
            children: [
              Expanded(
                child: PgQuickAction(
                  icon: const Icon(Icons.how_to_reg_rounded),
                  label: 'Admission',
                  color: AppColors.info,
                  onTap: () => context.push('/admissions'),
                ),
              ),
              Expanded(
                child: PgQuickAction(
                  icon: const Icon(Icons.event_note_rounded),
                  label: 'Leave',
                  color: AppColors.accent,
                  onTap: () => context.push('/leaves'),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }
}
