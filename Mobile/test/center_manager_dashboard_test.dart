import 'package:fieldtrack/core/auth/user_role.dart';
import 'package:fieldtrack/core/routing/route_permissions.dart';
import 'package:fieldtrack/modules/dashboard/screens/supervisor_dashboard_screen.dart';
import 'package:fieldtrack/modules/director/screens/director_dashboard_screen.dart';
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
    expect(RoutePermissions.canAccessPath('/manager/centers', role), isTrue);
    expect(RoutePermissions.canAccessPath('/manager/employees/create', role), isTrue);
    expect(RoutePermissions.canAccessPath('/manager/admissions', role), isTrue);
    expect(RoutePermissions.canAccessPath('/manager/admission-targets', role), isTrue);
    expect(RoutePermissions.canAccessPath('/manager/admission-targets/create', role), isTrue);
    expect(RoutePermissions.canAccessPath('/manager/reports', role), isTrue);
    expect(RoutePermissions.canAccessPath('/profile', role), isTrue);
    expect(RoutePermissions.canAccessPath('/attendance', role), isTrue);
    expect(RoutePermissions.canAccessPath('/attendance/punch-in', role), isTrue);
    expect(RoutePermissions.canAccessPath('/admissions', role), isFalse);
    expect(RoutePermissions.canAccessPath('/field-activities', role), isFalse);
    expect(RoutePermissions.canAccessPath('/manager/field-activities', role), isTrue);
  });

  test('Employee workflow routes stay available to employees', () {
    const role = UserRole.employee;

    expect(role.canAccessEmployeeWorkflow(), isTrue);
    expect(RoutePermissions.canAccessPath('/dashboard', role), isTrue);
    expect(RoutePermissions.canAccessPath('/attendance', role), isTrue);
    expect(RoutePermissions.canAccessPath('/admissions', role), isTrue);
    expect(RoutePermissions.canAccessPath('/leaves', role), isTrue);
    expect(RoutePermissions.canAccessPath('/field-activities', role), isTrue);
    expect(RoutePermissions.canAccessPath('/manager/employees', role), isFalse);
  });

  test('Project Manager uses director monitoring routes plus self attendance', () {
    const role = UserRole.projectHead;

    expect(role.canAccessDirectorRoutes(), isTrue);
    expect(role.canAccessOwnAttendance(), isTrue);
    expect(role.canAccessManagerRoutes(), isTrue);
    expect(RoutePermissions.canAccessPath('/director/centers', role), isTrue);
    expect(RoutePermissions.canAccessPath('/director/centers/3', role), isTrue);
    expect(RoutePermissions.canAccessPath('/director/employees', role), isTrue);
    expect(RoutePermissions.canAccessPath('/director/admissions', role), isTrue);
    expect(RoutePermissions.canAccessPath('/director/route-tracking', role), isTrue);
    expect(RoutePermissions.canAccessPath('/director/field-activities', role), isTrue);
    expect(RoutePermissions.canAccessPath('/attendance', role), isTrue);
    expect(RoutePermissions.canAccessPath('/attendance/punch-in', role), isTrue);
    expect(RoutePermissions.canAccessPath('/admissions', role), isFalse);
  });

  test('Director can open center drill-down routes and not manager create routes', () {
    const role = UserRole.director;

    expect(role.canAccessDirectorRoutes(), isTrue);
    expect(RoutePermissions.canAccessPath('/director/centers', role), isTrue);
    expect(RoutePermissions.canAccessPath('/director/centers/3', role), isTrue);
    expect(RoutePermissions.canAccessPath('/director/employees', role), isTrue);
    expect(RoutePermissions.canAccessPath('/director/admission-targets', role), isTrue);
    expect(RoutePermissions.canAccessPath('/director/reports', role), isTrue);
    expect(RoutePermissions.canAccessPath('/director/field-activities', role), isTrue);
    expect(RoutePermissions.canAccessPath('/manager/employees', role), isFalse);
    expect(RoutePermissions.canAccessPath('/manager/centers', role), isFalse);
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
    expect(find.text('Field Activities Today'), findsOneWidget);
    expect(find.text('Targets'), findsOneWidget);
    expect(find.text('My Attendance'), findsOneWidget);
    expect(find.text('Attendance Status'), findsOneWidget);
    expect(find.text('View Details'), findsOneWidget);
    expect(find.text('All Centers'), findsNothing);
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
      'My Centers',
      'Users / Employees',
      'Admissions',
      'Admission Targets',
      'Attendance',
      'Employee Routes',
      'Leave Requests',
      'Field Activities',
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

  testWidgets('Center Manager dashboard shows assigned-center selector', (
    tester,
  ) async {
    int? selected;
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SupervisorDashboardView(
            name: 'Anita Sharma',
            role: UserRole.centerManager,
            data: const {
              'employees': 4,
              'punched_in_today': 2,
              'punched_out_today': 0,
              'active_routes': 1,
              'pending_leaves': 0,
              'admission_targets': 4,
              'field_activities_today': 3,
            },
            centers: const [
              {'id': 1, 'name': 'Center 1'},
              {'id': 2, 'name': 'Center 2'},
            ],
            onSelectCenter: (value) => selected = value,
            onOpen: (_) {},
          ),
        ),
      ),
    );

    expect(find.text('All Centers'), findsOneWidget);
    expect(find.text('Center 1'), findsOneWidget);
    expect(find.text('Center 2'), findsOneWidget);
    expect(find.text('My Attendance'), findsOneWidget);

    await tester.tap(find.text('Center 2'));
    await tester.pump();
    expect(selected, 2);

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: SupervisorDashboardView(
            name: 'Anita Sharma',
            role: UserRole.centerManager,
            centerId: 2,
            data: const {
              'employees': 1,
              'punched_in_today': 1,
              'punched_out_today': 0,
              'active_routes': 1,
              'pending_leaves': 0,
              'admission_targets': 1,
              'field_activities_today': 0,
            },
            centers: const [
              {'id': 1, 'name': 'Center 1'},
              {'id': 2, 'name': 'Center 2'},
            ],
            onSelectCenter: (value) => selected = value,
            onOpen: (_) {},
          ),
        ),
      ),
    );
    await tester.pump();

    await tester.tap(find.text('All Centers'));
    await tester.pump();
    expect(selected, isNull);
  });

  testWidgets('Director dashboard shows monitoring cards only', (tester) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: DirectorDashboardView(
            name: 'Priya Rao',
            role: UserRole.director,
            data: const {
              'centers': 2,
              'active_centers': 2,
              'employees': 25,
              'punched_in_today': 18,
              'punched_out_today': 1,
              'active_routes': 2,
              'pending_leaves': 4,
              'pending_project_head_leaves': 1,
              'admission_targets': 5,
              'confirmed_admissions': 7,
            },
            onOpen: (_) {},
          ),
        ),
      ),
    );

    expect(find.text('Priya Rao'), findsOneWidget);
    expect(find.text('Director'), findsWidgets);
    expect(find.text('Total Centers'), findsOneWidget);
    expect(find.text('Employees'), findsOneWidget);
    expect(find.text('Punched In Today'), findsOneWidget);
    expect(find.text('18 / 25'), findsOneWidget);
    expect(find.text('Active Routes'), findsOneWidget);
    expect(find.text('Project Manager Leave'), findsOneWidget);
    expect(find.text('Pending: 1'), findsOneWidget);
    expect(find.text('Confirmed Admissions'), findsOneWidget);
    expect(find.text('Total: 7'), findsOneWidget);
    await tester.scrollUntilVisible(
      find.text('Field Activities Today'),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Field Activities Today'), findsOneWidget);
    expect(find.text('Attendance Status'), findsNothing);
    expect(find.text('View Details'), findsNothing);
    expect(find.text('My Attendance'), findsNothing);
    expect(find.text('Targets'), findsNothing);
    expect(find.text('Pending Leave'), findsNothing);
    expect(find.text('Modules'), findsNothing);
    expect(find.text('Admission Targets'), findsNothing);
    expect(find.text('Users / Employees'), findsNothing);
  });

  testWidgets('Project Manager dashboard matches Director monitoring plus self attendance', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: DirectorDashboardView(
            name: 'Rahul Patil',
            role: UserRole.projectHead,
            data: const {
              'centers': 2,
              'active_centers': 2,
              'employees': 8,
              'punched_in_today': 5,
              'punched_out_today': 1,
              'active_routes': 3,
              'pending_leaves': 2,
              'pending_project_head_leaves': 1,
              'admission_targets': 5,
              'confirmed_admissions': 4,
              'field_activities_today': 2,
            },
            onOpen: (_) {},
          ),
        ),
      ),
    );

    expect(find.text('Rahul Patil'), findsOneWidget);
    expect(find.text('Project Manager'), findsWidgets);
    expect(find.text('Attendance Status'), findsOneWidget);
    expect(find.text('View Details'), findsOneWidget);
    expect(find.text('Punch In'), findsOneWidget);
    expect(find.text('Punch Out'), findsOneWidget);
    expect(find.text('Duration'), findsOneWidget);
    expect(find.text('Not Punched In'), findsOneWidget);
    expect(find.text('Total Centers'), findsOneWidget);
    expect(find.text('Employees'), findsOneWidget);
    expect(find.text('Punched In Today'), findsOneWidget);
    expect(find.text('5 / 8'), findsOneWidget);
    expect(find.text('Active Routes'), findsOneWidget);
    expect(find.text('Confirmed Admissions'), findsOneWidget);
    expect(find.text('Total: 4'), findsOneWidget);
    expect(find.text('Project Manager Leave'), findsNothing);
    expect(find.text('Modules'), findsNothing);
    expect(find.text('Targets'), findsNothing);
    expect(find.text('My Attendance'), findsNothing);
    await tester.scrollUntilVisible(
      find.text('Field Activities Today'),
      200,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Field Activities Today'), findsOneWidget);
  });

  testWidgets('Director selected-center dashboard is monitoring only', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: DirectorDashboardView(
            name: 'Priya Rao',
            role: UserRole.director,
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
              'confirmed_admissions': 9,
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
    expect(find.text('Project Manager Leave'), findsNothing);
    expect(find.text('Employees'), findsOneWidget);
    expect(find.text('Punched In Today'), findsOneWidget);
    expect(find.text('1 / 3'), findsOneWidget);
    expect(find.text('Active Routes'), findsOneWidget);
    expect(find.text('Confirmed Admissions'), findsOneWidget);
    expect(find.text('Total: 9'), findsOneWidget);
    expect(find.text('Field Activities Today'), findsOneWidget);
    expect(find.text('Attendance Status'), findsNothing);
    expect(find.text('Targets'), findsNothing);
    expect(find.text('Modules'), findsNothing);
    expect(find.text('Admission Targets'), findsNothing);
    expect(find.text('Users / Employees'), findsNothing);
    expect(find.text('Leave Requests'), findsNothing);
    expect(find.text('Reports'), findsNothing);
  });
}
