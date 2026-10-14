import 'package:doubtqueue/screens/session_list_screen.dart';
import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:doubtqueue/services/api_service.dart';

import 'fake_backend.dart';

FakeBackend backendWithSessions() {
  final backend = FakeBackend(
    sessions: [
      FakeBackend.openSession(1, 'PHP Basics Doubt Session'),
      FakeBackend.openSession(
        2,
        'Flutter Layout Session',
        mentor: 'Arjun',
        createdAt: '2026-01-02 10:00:00',
      ),
      {
        ...FakeBackend.openSession(3, 'SQL Practice', mentor: 'Meera'),
        'status': 'CLOSED',
        'closed_at': '2026-01-01 12:00:00',
      },
    ],
  );
  backend.addDoubt(1, 'Asha');
  backend.addDoubt(1, 'Ben');
  return backend;
}

Future<void> pumpList(WidgetTester tester, FakeBackend backend) async {
  tester.view.physicalSize = const Size(800, 2000);
  tester.view.devicePixelRatio = 1.0;
  addTearDown(tester.view.reset);

  await tester.pumpWidget(
    MaterialApp(home: SessionListScreen(api: backend.api)),
  );
  await tester.pumpAndSettle();
}

double topOf(WidgetTester tester, String text) {
  return tester.getTopLeft(find.text(text)).dy;
}

void main() {

  testWidgets('shows a retryable error when loading sessions times out', (
    tester,
  ) async {
    final backend = backendWithSessions()..timeoutRequests = true;

    tester.view.physicalSize = const Size(800, 2000);
    tester.view.devicePixelRatio = 1.0;
    addTearDown(tester.view.reset);

    final api = ApiService(
      backend.client,
      baseUrl: 'http://test.local',
      timeout: const Duration(milliseconds: 10),
    );

    await tester.pumpWidget(
      MaterialApp(home: SessionListScreen(api: api)),
    );

    await tester.pump();
    await tester.pump(const Duration(milliseconds: 20));
    await tester.pump();

    expect(find.text('Could not load this page'), findsOneWidget);
    expect(
      find.text('The server took too long to answer. Try again.'),
      findsOneWidget,
    );
    expect(find.text('Try again'), findsOneWidget);

    backend.timeoutRequests = false;
    await tester.tap(find.text('Try again'));
    await tester.pump();
    await tester.pumpAndSettle();

    expect(find.text('PHP Basics Doubt Session'), findsOneWidget);
  });

  testWidgets('shows a card for each open session by default', (tester) async {
    await pumpList(tester, backendWithSessions());

    expect(find.text('PHP Basics Doubt Session'), findsOneWidget);
    expect(find.text('Mentor: Priya'), findsOneWidget);
    expect(find.text('2 students waiting'), findsOneWidget);
    expect(find.text('Flutter Layout Session'), findsOneWidget);
    expect(find.text('SQL Practice'), findsNothing);
    expect(find.text('Join queue'), findsNWidgets(2));
  });

  testWidgets('closed sessions have no join button', (tester) async {
    await pumpList(tester, backendWithSessions());

    await tester.tap(find.text('Closed'));
    await tester.pumpAndSettle();

    expect(find.text('SQL Practice'), findsOneWidget);
    expect(find.text('This session has ended.'), findsOneWidget);
    expect(find.text('Join queue'), findsNothing);
  });

  testWidgets('the All filter lists open sessions before closed ones', (
    tester,
  ) async {
    await pumpList(tester, backendWithSessions());

    await tester.tap(find.text('All'));
    await tester.pumpAndSettle();

    expect(find.text('Join queue'), findsNWidgets(2));
    expect(
      topOf(tester, 'Flutter Layout Session'),
      lessThan(topOf(tester, 'SQL Practice')),
    );
  });

  testWidgets('search filters by session title and mentor name', (
    tester,
  ) async {
    await pumpList(tester, backendWithSessions());

    await tester.enterText(find.byType(TextField), 'flutter');
    await tester.pumpAndSettle();
    expect(find.text('Flutter Layout Session'), findsOneWidget);
    expect(find.text('PHP Basics Doubt Session'), findsNothing);

    await tester.enterText(find.byType(TextField), 'priya');
    await tester.pumpAndSettle();
    expect(find.text('PHP Basics Doubt Session'), findsOneWidget);
    expect(find.text('Flutter Layout Session'), findsNothing);
  });

  testWidgets('search with no result shows an empty state', (tester) async {
    await pumpList(tester, backendWithSessions());

    await tester.enterText(find.byType(TextField), 'python');
    await tester.pumpAndSettle();

    expect(find.text('No sessions match your search'), findsOneWidget);

    await tester.tap(find.text('Clear search'));
    await tester.pumpAndSettle();

    expect(find.text('PHP Basics Doubt Session'), findsOneWidget);
  });

  testWidgets('sorting switches between newest and most waiting', (
    tester,
  ) async {
    await pumpList(tester, backendWithSessions());

    expect(
      topOf(tester, 'Flutter Layout Session'),
      lessThan(topOf(tester, 'PHP Basics Doubt Session')),
    );

    await tester.tap(find.text('Most waiting'));
    await tester.pumpAndSettle();

    expect(
      topOf(tester, 'PHP Basics Doubt Session'),
      lessThan(topOf(tester, 'Flutter Layout Session')),
    );
  });

  testWidgets('shows an empty state when there are no open sessions', (
    tester,
  ) async {
    await pumpList(tester, FakeBackend(sessions: []));

    expect(find.text('No open sessions right now'), findsOneWidget);
    expect(
      find.text('Check again later or ask your mentor to start a session.'),
      findsOneWidget,
    );
    expect(find.text('Refresh'), findsOneWidget);
  });

  testWidgets('shows an error state and recovers with Try again', (
    tester,
  ) async {
    final backend = backendWithSessions()..failRequests = true;
    await pumpList(tester, backend);

    expect(find.text('Could not load this page'), findsOneWidget);
    expect(find.text('Try again'), findsOneWidget);

    backend.failRequests = false;
    await tester.tap(find.text('Try again'));
    await tester.pumpAndSettle();

    expect(find.text('PHP Basics Doubt Session'), findsOneWidget);
  });

  testWidgets('shows a loading message while sessions load', (tester) async {
    final backend = backendWithSessions();
    await tester.pumpWidget(
      MaterialApp(home: SessionListScreen(api: backend.api)),
    );

    expect(find.text('Loading sessions...'), findsOneWidget);

    await tester.pumpAndSettle();
    expect(find.text('Loading sessions...'), findsNothing);
  });
}
