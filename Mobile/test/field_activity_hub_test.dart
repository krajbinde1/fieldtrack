import 'package:fieldtrack/modules/field_activities/screens/field_activity_hub_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:go_router/go_router.dart';

void main() {
  testWidgets('Field Activity hub shows Add Activity and My Activities', (tester) async {
    await tester.pumpWidget(
      MaterialApp.router(
        routerConfig: GoRouter(
          routes: [
            GoRoute(
              path: '/',
              builder: (_, _) => const FieldActivityHubScreen(),
            ),
          ],
        ),
      ),
    );

    expect(find.text('Field Activity'), findsOneWidget);
    expect(find.text('Add Activity'), findsOneWidget);
    expect(find.text('My Activities'), findsOneWidget);
  });
}
