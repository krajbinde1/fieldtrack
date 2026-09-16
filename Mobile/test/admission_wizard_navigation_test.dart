import 'package:fieldtrack/modules/admissions/screens/admission_wizard_screen.dart';
import 'package:flutter_test/flutter_test.dart';

void main() {
  test('Next visits every wizard step 1 through 5', () {
    var step = AdmissionWizardNavigation.firstIndex;
    final uiSteps = <int>[step + 1];

    while (step < AdmissionWizardNavigation.lastIndex) {
      final persisted = AdmissionWizardNavigation.draftStepOnNext(step);
      step = AdmissionWizardNavigation.next(step);
      // Draft current_step is 1-based destination; local index must not add it again.
      expect(persisted, step + 1);
      uiSteps.add(step + 1);
    }

    expect(uiSteps, [1, 2, 3, 4, 5]);
  });

  test('Back reverses every wizard step 5 through 1', () {
    var step = AdmissionWizardNavigation.lastIndex;
    final uiSteps = <int>[step + 1];

    while (step > AdmissionWizardNavigation.firstIndex) {
      step = AdmissionWizardNavigation.back(step);
      uiSteps.add(step + 1);
    }

    expect(uiSteps, [5, 4, 3, 2, 1]);
  });

  test('persisted current_step is not applied as an extra Next increment', () {
    var step = 0;
    final visited = <int>[];

    for (var i = 0; i < 4; i++) {
      final previous = step;
      final persistedCurrentStep =
          AdmissionWizardNavigation.draftStepOnNext(previous);
      final appliedFromApi = (persistedCurrentStep - 1).clamp(0, 4);
      step = AdmissionWizardNavigation.next(previous);
      visited.add(step + 1);

      expect(appliedFromApi, step);
      expect(step, previous + 1);
      expect(step, isNot(appliedFromApi + 1));
    }

    expect(visited, [2, 3, 4, 5]);
  });

  test('Save as Draft keeps the current 1-based step', () {
    for (var step = 0; step <= 4; step++) {
      expect(
        AdmissionWizardNavigation.draftStepOnSave(step),
        step + 1,
      );
    }
  });
}
