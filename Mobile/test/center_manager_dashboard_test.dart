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
    expect(RoutePermissions.canAccessPath('/attendance', role), isTrue);
    expect(RoutePermissions.canAccessPath('/attendance/punch-in', role), isTrue);
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

  test('Director can open center drill-down routes and not manager create routes', () {
    const role = UserRole.director;

    expect(role.canAccessDirectorRoutes(), isTrue);
    expect(RoutePermissions.canAccessPath('/director/centers', role), isTrue);
    expect(RoutePermissions.canAccessPath('/director/centers/3', role), isTrue);
    expect(RoutePermissions.canAccessPath('/director/employees', role), isTrue);
    expect(RoutePermissions.canAccessPath('/director/admission-targets', role), isTrue);
    expect(RoutePermissions.canAccessPath('/director/reports', role), isTrue);
    expect(RoutePermissions.canAccessPath('/manager/employees', role), isFalse);
    expect(RoutePermissions.canAccessPath('/manager/admission-targets/create', role), isFalse);
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
    expect(find.text('My Attendance'), findsOneWidget);
    expect(find.text('Attendance Status'), findsOneWidget);
    expect(find.text('View Details'), findsOneWidget);
    expect(find.text('Param FieldTrack'), findsNothing);
    expect(find.text('Submitted'), findsNothing);
    expect(find.text('Confirmed'), findsNothing);
    expect(find.text('Draft'), findsNothing);
    expect(find.text('Reverted'), findsNothing);
    expect(find.text('Rejected'), findsNothing);
    expect(find.text('Quick Actions'), findsNothing);
    expect(find.text('Set Admission Targets'), findsNothing);
    expect(find.text('Add User'), findsNothing);
    expect(find.text('Total Centers'), findsNothing);

    for (final label in [
      'Users / Employees',
      'Admissions',
      'Admission Targets',
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

  testWidgets('Director dashboard shows Total Centers card', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SupervisorDashboardView(
            name: 'Priya Rao',
            role: UserRole.director,
            data: const {
              'centers': 2,
              'active_centers': 2,
              'employees': 8,
              'punched_in_today': 3,
              'punched_out_today': 1,
              'active_routes': 2,
              'pending_leaves': 1,
              'admission_targets': 5,
            },
            onOpen: (_) {},
          ),
        ),
      ),
    );

    expect(find.text('Priya Rao'), findsOneWidget);
    expect(find.text('Director'), findsWidgets);
    expect(find.text('Total Centers'), findsOneWidget);
    expect(find.text('2'), findsWidgets);
    expect(find.text('My Attendance'), findsNothing);
    expect(find.text('Users / Employees'), findsNothing);
  });

  testWidgets('Director selected-center dashboard reuses center modules', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SupervisorDashboardView(
            name: 'Priya Rao',
            role: UserRole.director,
            directorCenterView: true,
            centerId: 4,
            centerName: 'Pune Center',
            schemeName: 'Demo Project',
            centerManagerName: 'Siddhesh Kaluse',
            data: const {
              'employees': 3,
              'punched_in_today': 1,
              'punched_out_today': 0,
              'active_routes': 1,
              'pending_leaves': 2,
              'admission_targets': 4,
              'admissions': 6,
            },
            onOpen: (_) {},
          ),
        ),
      ),
    );

    expect(find.text('Pune Center'), findsOneWidget);
    expect(find.text('Demo Project'), findsOneWidget);
    expect(find.text('Siddhesh Kaluse'), findsOneWidget);
    expect(find.text('Center Manager'), findsWidgets);
    expect(find.text('Total Centers'), findsNothing);
    expect(find.text('My Attendance'), findsNothing);
    expect(find.text('Employees'), findsWidgets);
    expect(find.text('Punched In'), findsWidgets);
    expect(find.text('Active Routes'), findsOneWidget);
    expect(find.text('Pending Leave'), findsOneWidget);
    expect(find.text('Targets'), findsOneWidget);

    await tester.drag(find.byType(Scrollable).first, const Offset(0, -1400));
    await tester.pumpAndSettle();

    expect(find.text('Users / Employees'), findsOneWidget);
    expect(find.text('Admissions'), findsWidgets);
    expect(find.text('Admission Targets'), findsOneWidget);
    expect(find.text('Attendance'), findsOneWidget);
    expect(find.text('Employee Routes'), findsOneWidget);
    expect(find.text('Leave Requests'), findsOneWidget);
    expect(find.text('Reports'), findsOneWidget);
  });
}
