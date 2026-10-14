import 'package:doubtqueue/models/session.dart';
import 'package:doubtqueue/screens/join_queue_screen.dart';
import 'package:doubtqueue/services/api_service.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';

import 'fake_backend.dart';

Future<FakeBackend> pumpJoin(
  WidgetTester tester, {
  bool closed = false,
  Duration timeout = const Duration(seconds: 15),
}) async {
  tester.view.physicalSize = const Size(800, 2000);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);

  final backend = FakeBackend();
  if (closed) {
    backend.sessions.first['status'] = 'CLOSED';
  }

  final session = Session.fromJson({
    ...backend.sessions.first,
    'waiting_count': 0,
  });

  await tester.pumpWidget(
    MaterialApp(
      home: JoinQueueScreen(
        api: ApiService(
          backend.client,
          baseUrl: 'http://test.local',
          timeout: timeout,
        ),
        session: session,
      ),
    ),
  );

  return backend;
}

Future<void> fillForm(WidgetTester tester) async {
  final fields = find.byType(TextFormField);
  await tester.enterText(fields.at(0), 'Asha');
  await tester.enterText(fields.at(1), 'asha@example.com');
  await tester.enterText(fields.at(2), 'Arrays');
  await tester.enterText(fields.at(3), 'How do loops work?');
}

Future<void> submit(WidgetTester tester) async {
  await tester.ensureVisible(find.byIcon(Icons.login));
  await tester.tap(find.byIcon(Icons.login));
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('re-enables Join queue after a request times out', (
    tester,
  ) async {
    final backend = await pumpJoin(
      tester,
      timeout: const Duration(milliseconds: 10),
    );

    backend.timeoutRequests = true;

    await fillForm(tester);
    await tester.ensureVisible(find.byIcon(Icons.login));
    await tester.tap(find.byIcon(Icons.login));

    await tester.pump();
    await tester.pump(const Duration(milliseconds: 20));
    await tester.pump();

    expect(
      find.text('The server took too long to answer. Try again.'),
      findsOneWidget,
    );
    expect(find.text('Join queue'), findsWidgets);
    expect(find.text('Joining...'), findsNothing);

    final button = tester.widget<FilledButton>(
      find
          .ancestor(
            of: find.text('Join queue'),
            matching: find.byType(FilledButton),
          )
          .first,
    );

    expect(button.onPressed, isNotNull);

    await tester.pumpWidget(const SizedBox());
  });

  testWidgets('shows validation messages for an empty form', (tester) async {
    await pumpJoin(tester);
    await submit(tester);

    expect(find.text('Enter your name'), findsOneWidget);
    expect(find.text('Enter your email address'), findsOneWidget);
    expect(find.text('Enter a topic'), findsOneWidget);
    expect(find.text('Enter your question'), findsOneWidget);
  });

  testWidgets('rejects an email without an at sign', (tester) async {
    await pumpJoin(tester);
    await fillForm(tester);
    await tester.enterText(
      find.byType(TextFormField).at(1),
      'asha.example.com',
    );

    await submit(tester);

    expect(find.text('Enter a valid email address'), findsOneWidget);
  });

  testWidgets('joining opens the my doubt screen with the queue position', (
    tester,
  ) async {
    await pumpJoin(tester);
    await fillForm(tester);

    await submit(tester);

    expect(find.text('My doubt'), findsOneWidget);
    expect(find.text('Your position'), findsOneWidget);
    expect(find.text('Students ahead of you: 0'), findsOneWidget);

    await tester.pumpWidget(const SizedBox());
  });

  testWidgets('shows the API message when the session is closed', (
    tester,
  ) async {
    await pumpJoin(tester, closed: true);
    await fillForm(tester);

    await submit(tester);

    expect(find.text('Session with id 1 is closed'), findsOneWidget);
    expect(find.text('Join queue'), findsWidgets);
  });
}
