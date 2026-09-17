import 'package:fieldtrack/core/auth/user_role.dart';
import 'package:fieldtrack/core/design/app_theme.dart';
import 'package:fieldtrack/core/routing/route_permissions.dart';
import 'package:fieldtrack/modules/admissions/models/admission_target.dart';
import 'package:fieldtrack/modules/attendance/models/attendance.dart';
import 'package:fieldtrack/modules/attendance/models/attendance_format.dart';
import 'package:fieldtrack/modules/dashboard/screens/employee_dashboard_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('Employee can open My Targets from admissions routes', () {
    const role = UserRole.employee;
    expect(RoutePermissions.canAccessPath('/admissions/targets', role), isTrue);
    expect(
      RoutePermissions.canAccessPath('/admissions/targets', UserRole.centerManager),
      isFalse,
    );
  });

  testWidgets('Employee dashboard shows welcome, modules and target card', (
    tester,
  ) async {
    await _pumpDashboard(tester);

    expect(tester.takeException(), isNull);
    expect(find.text('Param FieldTrack'), findsNothing);
    expect(find.text('Welcome back,'), findsOneWidget);
    expect(find.text('Emp A'), findsOneWidget);
    expect(find.text('Not Punched In'), findsOneWidget);
    expect(find.text('Punch In'), findsOneWidget);
    expect(find.text('Admission Target (This Week)'), findsOneWidget);
    expect(find.text('Target'), findsOneWidget);
    expect(find.text('Achieved'), findsOneWidget);
    expect(find.text('Remaining'), findsOneWidget);
    expect(find.text('Achievement %'), findsOneWidget);
    expect(find.text('Achieved 4 of 10'), findsOneWidget);
    expect(find.text('This Week'), findsWidgets);
    expect(find.text('Last Week'), findsNothing);
    expect(find.text('Last Month'), findsNothing);
    expect(find.byIcon(Icons.expand_more_rounded), findsOneWidget);
    expect(find.text('Attendance'), findsOneWidget);
    expect(find.text('Mark your attendance'), findsOneWidget);
    expect(find.text('History'), findsNothing);
    expect(find.text('Admission'), findsOneWidget);
    expect(find.text('Leave'), findsOneWidget);
    expect(find.text('My Targets'), findsOneWidget);
    expect(find.text('View targets & performance'), findsOneWidget);

    await tester.scrollUntilVisible(
      find.text('Field Activity'),
      240,
      scrollable: find.byType(Scrollable).first,
    );
    expect(find.text('Field Activity'), findsOneWidget);
    expect(find.text('Add and view field work'), findsOneWidget);
    expect(find.text('View targets & performance'), findsOneWidget);

    await tester.tap(find.byIcon(Icons.expand_more_rounded));
    await tester.pumpAndSettle();
    expect(find.text('Last Week'), findsOneWidget);
    expect(find.text('This Month'), findsOneWidget);
    expect(find.text('Last Month'), findsOneWidget);
  });

  testWidgets('Punch In opens the existing punch-in flow', (tester) async {
    final opened = <String>[];
    await _pumpDashboard(tester, onOpen: opened.add);

    await tester.tap(find.text('Punch In'));
    await tester.pump();

    expect(opened, ['/attendance/punch-in']);
  });

  testWidgets('Punched-in card shows time, location and Punch Out', (
    tester,
  ) async {
    final opened = <String>[];
    final punchIn = DateTime(2026, 9, 17, 9, 15);
    await _pumpDashboard(
      tester,
      locationName: 'Base HQ',
      attendance: Attendance(
        date: DateTime(2026, 9, 17),
        punchIn: punchIn,
        inAddress: 'Main Campus',
        status: 'Punched In',
      ),
      onOpen: opened.add,
    );

    expect(find.text('You are'), findsOneWidget);
    expect(find.text('Punched In'), findsOneWidget);
    expect(find.text(AttendanceFormat.time(punchIn)), findsOneWidget);
    expect(find.text('Main Campus'), findsOneWidget);
    expect(find.text('Base HQ'), findsNothing);
    expect(find.text('Punch Out'), findsOneWidget);
    expect(find.text('Punch In'), findsNothing);
    expect(find.text('Not Punched In'), findsNothing);

    await tester.tap(find.text('Punch Out'));
    await tester.pump();
    expect(opened, ['/attendance/punch-out']);
  });

  testWidgets('Punched-out card shows times, duration and no punch actions', (
    tester,
  ) async {
    final punchIn = DateTime(2026, 9, 17, 9, 15);
    final punchOut = DateTime(2026, 9, 17, 18, 30);
    await _pumpDashboard(
      tester,
      attendance: Attendance(
        date: DateTime(2026, 9, 17),
        punchIn: punchIn,
        punchOut: punchOut,
        workingHours: '9h 15m',
        status: 'Present',
      ),
    );

    expect(find.text('Punched Out'), findsOneWidget);
    expect(
      find.text(
        'In ${AttendanceFormat.time(punchIn)}  ·  Out ${AttendanceFormat.time(punchOut)}',
      ),
      findsOneWidget,
    );
    expect(find.text('9h 15m'), findsOneWidget);
    expect(find.text('Punch In'), findsNothing);
    expect(find.text('Punch Out'), findsNothing);
    expect(find.text('Not Punched In'), findsNothing);

    await _pumpDashboard(
      tester,
      attendance: Attendance(
        date: DateTime(2026, 9, 17),
        punchIn: DateTime(2026, 9, 17, 9),
        punchOut: DateTime(2026, 9, 17, 18, 5),
      ),
    );
    expect(find.text('9h 05m'), findsOneWidget);
  });
}

Future<void> _pumpDashboard(
  WidgetTester tester, {
  Attendance? attendance,
  bool attendanceLoading = false,
  String? locationName,
  ValueChanged<String>? onOpen,
}) async {
  tester.view.physicalSize = const Size(1080, 2400);
  tester.view.devicePixelRatio = 3;
  addTearDown(tester.view.resetPhysicalSize);
  addTearDown(tester.view.resetDevicePixelRatio);

  await tester.pumpWidget(
    MaterialApp(
      theme: AppTheme.light(),
      home: Scaffold(
        body: EmployeeDashboardView(
          name: 'Emp A',
          role: 'Employee',
          locationName: locationName,
          attendance: attendance,
          attendanceLoading: attendanceLoading,
          preset: 'this_week',
          loading: false,
          summary: const AdmissionTargetSummary(
            target: 10,
            achieved: 4,
            remaining: 6,
            percentage: 40,
          ),
          onPreset: (_) {},
          onRetry: () {},
          onOpen: onOpen ?? (_) {},
        ),
      ),
    ),
  );
}
