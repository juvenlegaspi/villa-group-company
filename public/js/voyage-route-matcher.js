(function (root, factory) {
    const matcher = factory();
    if (typeof module === 'object' && module.exports) module.exports = matcher;
    if (root) root.VoyageRouteMatcher = matcher;
})(typeof window !== 'undefined' ? window : globalThis, function () {
    'use strict';

    const EARTH_RADIUS_NM = 3440.065;
    const DEFAULT_DEVIATION_THRESHOLD_NM = 5;

    const normalizeLongitude = (longitude) => ((longitude + 540) % 360) - 180;
    const longitudeDelta = (from, to) => normalizeLongitude(to - from);
    const validPoint = (point) => Array.isArray(point)
        && Number.isFinite(Number(point[0]))
        && Number.isFinite(Number(point[1]))
        && Number(point[1]) >= -90
        && Number(point[1]) <= 90;
    const point = (value) => [normalizeLongitude(Number(value[0])), Number(value[1])];
    const distanceNm = (first, second) => {
        const latitudeDelta = (second[1] - first[1]) * Math.PI / 180;
        const longitudeDifference = longitudeDelta(first[0], second[0]) * Math.PI / 180;
        const latitudeA = first[1] * Math.PI / 180;
        const latitudeB = second[1] * Math.PI / 180;
        const value = Math.sin(latitudeDelta / 2) ** 2
            + Math.cos(latitudeA) * Math.cos(latitudeB) * Math.sin(longitudeDifference / 2) ** 2;
        return EARTH_RADIUS_NM * 2 * Math.asin(Math.min(1, Math.sqrt(value)));
    };

    const closestOnSegment = (reported, start, end, minimumFraction = 0) => {
        const latitudeScale = Math.max(0.01, Math.cos(reported[1] * Math.PI / 180));
        const segmentX = longitudeDelta(start[0], end[0]) * latitudeScale;
        const segmentY = end[1] - start[1];
        const pointX = longitudeDelta(start[0], reported[0]) * latitudeScale;
        const pointY = reported[1] - start[1];
        const lengthSquared = segmentX ** 2 + segmentY ** 2;
        const rawFraction = lengthSquared > 0 ? ((pointX * segmentX) + (pointY * segmentY)) / lengthSquared : 0;
        const fraction = Math.max(minimumFraction, Math.min(1, rawFraction));
        const matched = [
            normalizeLongitude(start[0] + (longitudeDelta(start[0], end[0]) * fraction)),
            start[1] + ((end[1] - start[1]) * fraction),
        ];

        return { matched, fraction, deviationNm: distanceNm(reported, matched) };
    };

    const closestForwardMatch = (route, reported, minimumProgress) => {
        const firstSegment = Math.min(route.length - 2, Math.max(0, Math.floor(minimumProgress)));
        let best = null;

        for (let index = firstSegment; index < route.length - 1; index++) {
            const minimumFraction = index === firstSegment ? Math.max(0, minimumProgress - index) : 0;
            const candidate = closestOnSegment(reported, route[index], route[index + 1], minimumFraction);
            candidate.progress = index + candidate.fraction;
            if (!best || candidate.deviationNm < best.deviationNm) best = candidate;
        }

        return best;
    };

    const match = (plannedCoordinates, reportedCoordinates, options = {}) => {
        const route = (Array.isArray(plannedCoordinates) ? plannedCoordinates : []).filter(validPoint).map(point);
        const reports = (Array.isArray(reportedCoordinates) ? reportedCoordinates : []).filter(validPoint).map(point);
        const completionPoint = validPoint(options.completionPoint) ? point(options.completionPoint) : null;
        const thresholdNm = Number.isFinite(Number(options.deviationThresholdNm))
            ? Math.max(0, Number(options.deviationThresholdNm))
            : DEFAULT_DEVIATION_THRESHOLD_NM;

        const appendUnique = (collection, coordinate) => {
            if (!coordinate) return;
            const previous = collection.at(-1);
            if (!previous || distanceNm(previous, coordinate) > 0.000001) collection.push([...coordinate]);
        };

        if (route.length < 2) {
            const track = reports.map((coordinate) => [...coordinate]);
            if (options.completed) appendUnique(track, completionPoint);
            return {
                track,
                matches: [],
                latestMatch: null,
                maxDeviationNm: null,
                hasDeviation: false,
                followsPlannedRoute: false,
                usesPlannedCompletion: false,
            };
        }

        const matches = [];
        let minimumProgress = 0;
        reports.forEach((reported) => {
            const result = closestForwardMatch(route, reported, minimumProgress);
            if (!result) return;
            minimumProgress = Math.max(minimumProgress, result.progress);
            matches.push({ reported, ...result });
        });

        const maxDeviationNm = matches.length ? Math.max(...matches.map((item) => item.deviationNm)) : null;
        const latestMatch = matches.at(-1) || null;
        const track = reports.map((coordinate) => [...coordinate]);
        let completionMatch = null;
        let usesPlannedCompletion = false;

        if (options.completed && completionPoint) {
            const startMatch = latestMatch || { matched: route[0], progress: 0 };
            completionMatch = closestForwardMatch(route, completionPoint, startMatch.progress);

            if (completionMatch) {
                // Keep every actual report untouched. From the final genuine
                // report, connect to its nearest forward point on the approved
                // route, follow the remaining saved route vertices, then end
                // at the confirmed completion pin.
                appendUnique(track, startMatch.matched);
                const firstRouteVertex = Math.floor(startMatch.progress) + 1;
                const lastRouteVertex = Math.floor(completionMatch.progress);
                for (let index = firstRouteVertex; index <= lastRouteVertex && index < route.length; index++) {
                    appendUnique(track, route[index]);
                }
                appendUnique(track, completionMatch.matched);
                appendUnique(track, completionPoint);
                usesPlannedCompletion = true;
            }
        }

        return {
            // Preserve the approved route separately and draw the tracking line
            // through the coordinates that were actually reported. Matching is
            // used only for progress/deviation analysis; it must never move a
            // vessel report onto the planned line.
            track,
            matches,
            latestMatch,
            completionMatch,
            maxDeviationNm,
            hasDeviation: maxDeviationNm !== null && maxDeviationNm > thresholdNm,
            followsPlannedRoute: true,
            usesPlannedCompletion,
            deviationThresholdNm: thresholdNm,
        };
    };

    return { match, distanceNm };
});
