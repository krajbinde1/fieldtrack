import 'package:fieldtrack/modules/admissions/models/admission.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('Admission lookups map district ids from API JSON', () {
    final lookups = AdmissionLookups.fromJson({
      'state': 'Maharashtra',
      'districts': [
        {'id': '12', 'name': 'Pune', 'code': 'PUNE'},
        {'id': 18, 'name': 'Nashik', 'code': 'NASHIK'},
      ],
    });

    expect(lookups.state, 'Maharashtra');
    expect(lookups.districts, hasLength(2));
    expect(lookups.districts.first.id, 12);
    expect(lookups.districts.first.name, 'Pune');
    expect(lookups.districts.last.id, 18);
  });

  testWidgets('District and taluka dropdowns show mapped lookup names', (
    tester,
  ) async {
    final lookups = AdmissionLookups.fromJson({
      'state': 'Maharashtra',
      'districts': [
        {'id': 1, 'name': 'Pune', 'code': 'PUNE'},
        {'id': 2, 'name': 'Nashik', 'code': 'NASHIK'},
      ],
    });
    final talukas = [
      NamedLookup.fromJson({'id': 11, 'name': 'Haveli', 'code': 'HAVELI'}),
      NamedLookup.fromJson({'id': 12, 'name': 'Mulshi', 'code': 'MULSHI'}),
    ];

    await tester.pumpWidget(
      MaterialApp(
        home: Scaffold(
          body: Column(
            children: [
              Text(lookups.state),
              DropdownButtonFormField<int>(
                decoration: const InputDecoration(labelText: 'District'),
                items: [
                  for (final district in lookups.districts)
                    DropdownMenuItem(
                      value: district.id,
                      child: Text(district.name),
                    ),
                ],
                onChanged: (_) {},
              ),
              DropdownButtonFormField<int>(
                decoration: const InputDecoration(labelText: 'Taluka'),
                items: [
                  for (final taluka in talukas)
                    DropdownMenuItem(
                      value: taluka.id,
                      child: Text(taluka.name),
                    ),
                ],
                onChanged: (_) {},
              ),
            ],
          ),
        ),
      ),
    );

    expect(find.text('Maharashtra'), findsOneWidget);

    await tester.tap(find.byType(DropdownButtonFormField<int>).first);
    await tester.pumpAndSettle();
    expect(find.text('Pune').last, findsOneWidget);
    expect(find.text('Nashik').last, findsOneWidget);

    await tester.tap(find.text('Pune').last);
    await tester.pumpAndSettle();

    await tester.tap(find.byType(DropdownButtonFormField<int>).last);
    await tester.pumpAndSettle();
    expect(find.text('Haveli').last, findsOneWidget);
    expect(find.text('Mulshi').last, findsOneWidget);
    expect(find.text('Igatpuri'), findsNothing);
  });
}
