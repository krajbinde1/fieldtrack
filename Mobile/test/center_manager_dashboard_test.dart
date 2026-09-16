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
    expect(RoutePermissions.canAccessPath('/manager/admissions', role), isTrue);
    expect(RoutePermissions.canAccessPath('/manager/admission-targets', role), isTrue);
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
            name: 'Center Manager',
            role: UserRole.centerManager,
            data: const {
              'employees': 3,
              'punched_in_today': 1,
              'active_routes': 1,
              'admissions': 2,
              'pending_leaves': 0,
              'admission_targets': 4,
            },
            onOpen: (_) {},
          ),
        ),
      ),
    );

    expect(tester.takeException(), isNull);
    expect(find.text('Center Manager'), findsWidgets);
    expect(find.text('Employees'), findsOneWidget);
    expect(find.text('Users / Employees'), findsOneWidget);
    expect(find.text('Admissions'), findsWidgets);

    for (final label in [
      'Admission Targets',
      'Leave Requests',
      'Attendance',
      'Employee Routes',
      'Profile',
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
