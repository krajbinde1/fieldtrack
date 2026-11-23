import 'package:fieldtrack/core/design/app_colors.dart';
import 'package:fieldtrack/modules/expenses/widgets/expense_approval_timeline.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  testWidgets('timeline shows green, orange, grey, and red stages', (
    tester,
  ) async {
    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: ExpenseApprovalTimeline(
            pendingText: 'Center Manager Approval — waiting on CM',
            stages: [
              ExpenseTimelineStage.fromJson(const {
                'key': 'created',
                'title': 'Request Created',
                'person_name': 'Emp A',
                'role': 'Mobilizer',
                'date': '23 Nov 2026',
                'time': '10:15 AM',
                'status': 'completed',
                'status_label': 'Submitted',
                'color': 'green',
              }),
              ExpenseTimelineStage.fromJson(const {
                'key': 'center_manager',
                'title': 'Center Manager Approval',
                'person_name': 'CM',
                'role': 'Center Manager',
                'status': 'pending',
                'status_label': 'Pending',
                'color': 'orange',
                'pending_names': ['CM'],
              }),
              ExpenseTimelineStage.fromJson(const {
                'key': 'center_coordinator',
                'title': 'Center Coordinator Approval',
                'role': 'Center Coordinator',
                'status': 'rejected',
                'status_label': 'Rejected',
                'color': 'red',
                'person_name': 'Ravi Coordinator',
                'remarks': 'Bill is unclear',
              }),
              ExpenseTimelineStage.fromJson(const {
                'key': 'final',
                'title': 'Admin/Director Final Approval',
                'role': 'Admin / Director',
                'status': 'not_started',
                'status_label': 'Not Started',
                'color': 'grey',
              }),
              ExpenseTimelineStage.fromJson(const {
                'key': 'payment',
                'title': 'Payment Done',
                'status': 'not_started',
                'status_label': 'Not Started',
                'color': 'grey',
              }),
            ],
          ),
        ),
      ),
    );

    expect(find.text('Request Created'), findsOneWidget);
    expect(find.text('Emp A'), findsOneWidget);
    expect(find.text('Mobilizer'), findsOneWidget);
    expect(find.text('23 Nov 2026 · 10:15 AM'), findsOneWidget);
    expect(
      find.text('Center Manager Approval — waiting on CM'),
      findsOneWidget,
    );
    expect(find.text('Bill is unclear'), findsOneWidget);
    expect(find.text('Not Started'), findsNWidgets(2));

    expect(_badgeColor(tester, 'created'), AppColors.approvedBg);
    expect(_dotColor(tester, 'created'), AppColors.success);
    expect(_badgeColor(tester, 'center_manager'), AppColors.pendingBg);
    expect(_dotColor(tester, 'center_manager'), AppColors.warning);
    expect(_badgeColor(tester, 'center_coordinator'), AppColors.rejectedBg);
    expect(_dotColor(tester, 'center_coordinator'), AppColors.error);
    expect(_badgeColor(tester, 'payment'), const Color(0xFFF3F4F6));
    expect(_dotColor(tester, 'final'), Colors.white);
  });

  testWidgets('skipped manager stage is not shown', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: ExpenseApprovalTimeline(
            pendingText:
                'Center Coordinator Approval — waiting on Assigned Coordinator',
            stages: [
              ExpenseTimelineStage(
                keyName: 'created',
                title: 'Request Created',
                personName: 'CM',
                role: 'Center Manager',
                status: 'completed',
                statusLabel: 'Submitted',
                color: 'green',
              ),
              ExpenseTimelineStage(
                keyName: 'center_coordinator',
                title: 'Center Coordinator Approval',
                personName: 'Assigned Coordinator',
                role: 'Center Coordinator',
                status: 'pending',
                statusLabel: 'Pending',
                color: 'orange',
              ),
              ExpenseTimelineStage(
                keyName: 'final',
                title: 'Admin/Director Final Approval',
                role: 'Admin / Director',
                status: 'not_started',
                statusLabel: 'Not Started',
                color: 'grey',
              ),
            ],
          ),
        ),
      ),
    );

    expect(find.text('Center Manager Approval'), findsNothing);
    expect(find.text('Request Created'), findsOneWidget);
    expect(find.text('CM'), findsOneWidget);
    expect(find.text('Center Coordinator Approval'), findsOneWidget);
    expect(find.text('Assigned Coordinator'), findsOneWidget);
    expect(find.text('No approver is assigned'), findsNothing);
  });

  testWidgets('missing approver is named on the pending stage', (tester) async {
    await tester.pumpWidget(
      const MaterialApp(
        home: Scaffold(
          body: ExpenseApprovalTimeline(
            pendingText:
                'Center Coordinator Approval — no approver is assigned',
            stages: [
              ExpenseTimelineStage(
                keyName: 'center_coordinator',
                title: 'Center Coordinator Approval',
                role: 'Center Coordinator',
                status: 'pending',
                statusLabel: 'Pending',
                color: 'orange',
                detail: 'No approver is assigned',
              ),
            ],
          ),
        ),
      ),
    );

    expect(find.text('No approver is assigned'), findsOneWidget);
    expect(
      find.text('Center Coordinator Approval — no approver is assigned'),
      findsOneWidget,
    );
  });
}

Color _badgeColor(WidgetTester tester, String key) {
  final badge = tester.widget<Container>(
    find.byKey(Key('expense-timeline-badge-$key')),
  );
  return (badge.decoration! as BoxDecoration).color!;
}

Color _dotColor(WidgetTester tester, String key) {
  final dot = tester.widget<Container>(
    find.byKey(Key('expense-timeline-dot-$key')),
  );
  return (dot.decoration! as BoxDecoration).color!;
}
