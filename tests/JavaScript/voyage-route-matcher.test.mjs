import assert from 'node:assert/strict';
import '../../public/js/voyage-route-matcher.js';

const matcher = globalThis.VoyageRouteMatcher;
const plannedRoute = [[120, 10], [121, 10], [122, 10]];
const actualReports = [[120, 10], [120.5, 11], [121.5, 11]];

const activeResult = matcher.match(plannedRoute, actualReports, { deviationThresholdNm: 5 });
assert.deepEqual(activeResult.track, actualReports, 'The visible track must use actual reported coordinates.');
assert.equal(activeResult.hasDeviation, true, 'An off-route report must still raise a deviation.');
assert.notDeepEqual(activeResult.latestMatch.matched, activeResult.latestMatch.reported);

const completionPoint = [122, 10];
const completedResult = matcher.match(plannedRoute, actualReports, { completed: true, completionPoint });
assert.deepEqual(
    completedResult.track.slice(0, actualReports.length),
    actualReports,
    'Completion must preserve every actual report before extending the track.',
);
assert.deepEqual(completedResult.track.at(-1), completionPoint, 'The completed track must end at the confirmed arrival pin.');
assert.equal(completedResult.usesPlannedCompletion, true, 'Completion must follow the remaining approved route.');
assert.ok(
    completedResult.track.some(coordinate => coordinate[0] === 122 && coordinate[1] === 10),
    'The completed track must include the remaining planned route.',
);

const noPlanResult = matcher.match([], actualReports);
assert.deepEqual(noPlanResult.track, actualReports);

const noPlanCompletedResult = matcher.match([], actualReports, { completed: true, completionPoint });
assert.deepEqual(noPlanCompletedResult.track.at(-1), completionPoint);
assert.equal(noPlanCompletedResult.usesPlannedCompletion, false);

console.log('voyage-route-matcher tests passed');
