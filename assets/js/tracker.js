(function () {
  'use strict';

  var visitId = null;
  var startTime = Date.now();
  var durationSent = false;

  function post(data) {
    return fetch('/track.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      credentials: 'same-origin',
      body: JSON.stringify(data)
    }).then(function (res) {
      return res.json().catch(function () { return {}; });
    }).catch(function () {
      return {};
    });
  }

  function trackBasic() {
    var payload = {
      url: window.location.href,
      screen_width: window.screen && window.screen.width ? window.screen.width : null,
      screen_height: window.screen && window.screen.height ? window.screen.height : null,
      language: navigator.language || navigator.userLanguage || null
    };

    post(payload).then(function (data) {
      if (data && data.ok && data.id) {
        visitId = data.id;
        window.__TRACK_VISIT_ID = visitId;
      }
    });
  }

  function sendDuration() {
    if (durationSent) return;
    if (!visitId) return;
    var now = Date.now();
    var seconds = Math.round((now - startTime) / 1000);
    if (!seconds || seconds < 0) return;

    var payload = {
      visit_id: visitId,
      duration_seconds: seconds
    };

    durationSent = true;

    try {
      if (navigator.sendBeacon) {
        var blob = new Blob([JSON.stringify(payload)], { type: 'application/json' });
        navigator.sendBeacon('/track.php', blob);
      } else {
        fetch('/track.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify(payload),
          keepalive: true
        }).catch(function () {});
      }
    } catch (e) {
    }
  }

  function requestGeo() {
    if (!navigator.geolocation) {
      return;
    }

    var sendCoords = function (coords) {
      var payload = {
        visit_id: visitId || 0,
        latitude: coords.latitude,
        longitude: coords.longitude
      };

      if (!visitId) {
        trackBasic();
        setTimeout(function () {
          payload.visit_id = window.__TRACK_VISIT_ID || 0;
          post(payload);
        }, 800);
      } else {
        post(payload);
      }
    };

    navigator.geolocation.getCurrentPosition(function (position) {
      sendCoords(position.coords);
    }, function (error) {
      console.warn('Geolocation error', error);
    }, {
      enableHighAccuracy: true,
      timeout: 8000,
      maximumAge: 0
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    trackBasic();
  });

  window.addEventListener('pagehide', sendDuration);
  window.addEventListener('beforeunload', sendDuration);

  window.Tracker = {
    trackBasic: trackBasic,
    requestGeo: requestGeo
  };
})();
