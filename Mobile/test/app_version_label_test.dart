import 'package:fieldtrack/core/widgets/app_version_label.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:package_info_plus/package_info_plus.dart';

void main() {
  testWidgets('shows version and build from package info', (tester) async {
    PackageInfo.setMockInitialValues(
      appName: 'fieldtrack',
      packageName: 'com.param.fieldtrack',
      version: '1.0.2',
      buildNumber: '3',
      buildSignature: '',
    );

    await tester.pumpWidget(
      const MaterialApp(home: Scaffold(body: AppVersionLabel())),
    );
    await tester.pump();

    expect(find.text('Version 1.0.2'), findsOneWidget);
    expect(find.text('Build 3'), findsOneWidget);
    expect(find.textContaining('1.0.2 (Build'), findsNothing);
  });
}
