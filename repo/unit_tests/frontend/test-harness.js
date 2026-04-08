/**
 * TestHarness - Minimal browser-based test framework for RideCircle frontend tests.
 *
 * Usage:
 *   TestHarness.suite('Suite Name');
 *   TestHarness.test('test name', function() {
 *       TestHarness.assertEqual(actual, expected, 'optional message');
 *   });
 *   TestHarness.render();
 */
var TestHarness = (function() {
    var results = [];
    var currentSuite = '';

    return {
        suite: function(name) { currentSuite = name; },

        test: function(name, fn) {
            try {
                fn();
                results.push({suite: currentSuite, name: name, passed: true});
            } catch(e) {
                results.push({suite: currentSuite, name: name, passed: false, error: e.message});
            }
        },

        assertEqual: function(actual, expected, msg) {
            if (actual !== expected) throw new Error((msg || '') + ' Expected ' + JSON.stringify(expected) + ' but got ' + JSON.stringify(actual));
        },

        assertDeepEqual: function(actual, expected, msg) {
            if (JSON.stringify(actual) !== JSON.stringify(expected)) {
                throw new Error((msg || '') + ' Expected ' + JSON.stringify(expected) + ' but got ' + JSON.stringify(actual));
            }
        },

        assertTrue: function(val, msg) {
            if (!val) throw new Error(msg || 'Expected truthy value');
        },

        assertFalse: function(val, msg) {
            if (val) throw new Error(msg || 'Expected falsy value');
        },

        assertContains: function(str, substr, msg) {
            if (String(str).indexOf(substr) === -1) throw new Error((msg || '') + ' Expected "' + str + '" to contain "' + substr + '"');
        },

        assertNotContains: function(str, substr, msg) {
            if (String(str).indexOf(substr) !== -1) throw new Error((msg || '') + ' Expected "' + str + '" to NOT contain "' + substr + '"');
        },

        assertGreaterThan: function(a, b, msg) {
            if (!(a > b)) throw new Error((msg || '') + ' Expected ' + a + ' > ' + b);
        },

        assertLessThanOrEqual: function(a, b, msg) {
            if (!(a <= b)) throw new Error((msg || '') + ' Expected ' + a + ' <= ' + b);
        },

        assertThrows: function(fn, msg) {
            var threw = false;
            try { fn(); } catch(e) { threw = true; }
            if (!threw) throw new Error(msg || 'Expected function to throw');
        },

        assertMatch: function(str, regex, msg) {
            if (!regex.test(str)) throw new Error((msg || '') + ' Expected "' + str + '" to match ' + regex);
        },

        render: function() {
            var container = document.getElementById('test-results');
            var passed = results.filter(function(r) { return r.passed; }).length;
            var failed = results.filter(function(r) { return !r.passed; }).length;

            var html = '<h2 style="font-family:sans-serif;">Test Results: ' +
                '<span style="color:green;">' + passed + ' passed</span>, ' +
                '<span style="color:' + (failed > 0 ? 'red' : 'green') + ';">' + failed + ' failed</span></h2>';
            var lastSuite = '';
            results.forEach(function(r) {
                if (r.suite !== lastSuite) {
                    html += '<h3 style="font-family:sans-serif;margin-top:16px;border-bottom:1px solid #ddd;padding-bottom:4px;">' + r.suite + '</h3>';
                    lastSuite = r.suite;
                }
                var color = r.passed ? 'green' : 'red';
                var icon = r.passed ? '&#10003;' : '&#10007;';
                html += '<div style="color:' + color + ';margin:4px 0;font-family:monospace;font-size:14px;">' + icon + ' ' + r.name;
                if (!r.passed) html += ' &mdash; <span style="color:#999">' + r.error + '</span>';
                html += '</div>';
            });
            container.innerHTML = html;

            // Update document title with summary
            document.title = (failed > 0 ? 'FAIL' : 'PASS') + ' (' + passed + '/' + (passed + failed) + ') - ' + document.title;
        },

        reset: function() {
            results = [];
            currentSuite = '';
        },

        getResults: function() {
            return {
                passed: results.filter(function(r) { return r.passed; }).length,
                failed: results.filter(function(r) { return !r.passed; }).length,
                results: results
            };
        }
    };
})();
