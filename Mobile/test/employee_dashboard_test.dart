import 'package:fieldtrack/core/auth/user_role.dart';
import 'package:fieldtrack/core/design/app_theme.dart';
import 'package:fieldtrack/core/routing/route_permissions.dart';
import 'package:fieldtrack/core/widgets/design/pg_card.dart';
import 'package:fieldtrack/modules/admissions/models/admission_target.dart';
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
            onOpen: (_) {},
          ),
        ),
      ),
    );

    expect(tester.takeException(), isNull);
    expect(find.text('Param FieldTrack'), findsNothing);
    expect(find.text('Welcome back,'), findsOneWidget);
    expect(find.text('Emp A'), findsOneWidget);
    expect(find.text('Admission Target Performance'), findsOneWidget);
    expect(find.text('Target'), findsOneWidget);
    expect(find.text('Achieved'), findsOneWidget);
    expect(find.text('Remaining'), findsOneWidget);
    expect(find.text('%'), findsOneWidget);
    expect(find.text('This Week'), findsOneWidget);
    expect(find.text('Last Week'), findsNothing);
    expect(find.text('Last Month'), findsNothing);
    expect(find.byIcon(Icons.filter_alt), findsOneWidget);
    expect(find.text('Attendance'), findsOneWidget);
    expect(find.text('History'), findsOneWidget);
    expect(find.text('Admission'), findsOneWidget);
    expect(find.text('Leave'), findsOneWidget);
    expect(find.text('My Targets'), findsOneWidget);

    await tester.tap(find.byIcon(Icons.filter_alt));
    await tester.pumpAndSettle();
    expect(find.text('Last Week'), findsOneWidget);
    expect(find.text('This Month'), findsOneWidget);
    expect(find.text('Last Month'), findsOneWidget);

    final moduleCards = tester.widgetList(find.byType(PgCard)).skip(1).toList();
    expect(moduleCards, hasLength(5));
    final firstSize = tester.getSize(find.byWidget(moduleCards.first));
    final lastSize = tester.getSize(find.byWidget(moduleCards.last));
    expect(firstSize.width, moreOrLessEquals(lastSize.width, epsilon: 2));
    expect(firstSize.height, moreOrLessEquals(lastSize.height, epsilon: 2));
  });
}
