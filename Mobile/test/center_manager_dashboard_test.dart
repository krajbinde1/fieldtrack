import 'package:fieldtrack/core/auth/user_role.dart';
import 'package:fieldtrack/core/routing/route_permissions.dart';
import 'package:fieldtrack/modules/dashboard/screens/supervisor_dashboard_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('Center Manager maps to manager routes and not employee workflow', () {
    const role = UserRole.centerManager;

    expect(UserRole.fromValue('center_manager'), role);
    expect(role.canAccessManagerRoutes(), isTrue);
    expect(role.canAccessEmployeeWorkflow(), isFalse);
    expect(RoutePermissions.canAccessPath('/dashboard', role), isTrue);
    expect(RoutePermissions.canAccessPath('/manager/employees', role), isTrue);
    expect(RoutePermissions.canAccessPath('/manager/employees/create', role), isTrue);
    expect(RoutePermissions.canAccessPath('/manager/admissions', role), isTrue);
    expect(RoutePermissions.canAccessPath('/manager/admission-targets', role), isTrue);
    expect(RoutePermissions.canAccessPath('/manager/admission-targets/create', role), isTrue);
    expect(RoutePermissions.canAccessPath('/manager/reports', role), isTrue);
    expect(RoutePermissions.canAccessPath('/profile', role), isTrue);
    expect(RoutePermissions.canAccessPath('/attendance', role), isFalse);
    expect(RoutePermissions.canAccessPath('/admissions', role), isFalse);
  });

  test('Employee workflow routes stay available to employees', () {
    const role = UserRole.employee;

    expect(role.canAccessEmployeeWorkflow(), isTrue);
    expect(RoutePermissions.canAccessPath('/dashboard', role), isTrue);
    expect(RoutePermissions.canAccessPath('/attendance', role), isTrue);
    expect(RoutePermissions.canAccessPath('/admissions', role), isTrue);
    expect(RoutePermissions.canAccessPath('/leaves', role), isTrue);
    expect(RoutePermissions.canAccessPath('/manager/employees', role), isFalse);
  });

  testWidgets('Center Manager dashboard body shows summary and modules', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SupervisorDashboardView(
            name: 'Anita Sharma',
            role: UserRole.centerManager,
            data: const {
              'employees': 3,
              'punched_in_today': 1,
              'punched_out_today': 0,
              'active_routes': 1,
              'pending_leaves': 0,
              'admission_targets': 4,
            },
            onOpen: (_) {},
          ),
        ),
      ),
    );

    expect(tester.takeException(), isNull);
    expect(find.text('Anita Sharma'), findsOneWidget);
    expect(find.text('Center Manager'), findsWidgets);
    expect(find.text('Welcome back,'), findsOneWidget);
    expect(find.text('Employees'), findsWidgets);
    expect(find.text('Punched In'), findsWidgets);
    expect(find.text('Active Routes'), findsOneWidget);
    expect(find.text('Pending Leave'), findsOneWidget);
    expect(find.text('Targets'), findsOneWidget);
    expect(find.text('Attendance Status'), findsOneWidget);
    expect(find.text('View Details'), findsOneWidget);
    expect(find.text('Param FieldTrack'), findsNothing);
    expect(find.text('Submitted'), findsNothing);
    expect(find.text('Confirmed'), findsNothing);
    expect(find.text('Draft'), findsNothing);
    expect(find.text('Reverted'), findsNothing);
    expect(find.text('Rejected'), findsNothing);

    for (final label in [
      'Quick Actions',
      'Set Admission Targets',
      'Add User',
      'Users / Employees',
      'Admissions',
      'Attendance',
      'Employee Routes',
      'Leave Requests',
      'Reports',
    ]) {
      await tester.scrollUntilVisible(
        find.text(label),
        240,
        scrollable: find.byType(Scrollable).first,
      );
      expect(find.text(label), findsWidgets);
    }
  });
}
