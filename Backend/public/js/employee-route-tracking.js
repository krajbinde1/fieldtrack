/* Employee Routes map: historical playback and live GPS tracking.
   The live marker moves only between two fixes the server has already stored.
   An empty poll, a duplicate, or an older timestamp does not move it. */
(function () {
    const BLUE = '#2563eb';
    const BLUE_FAINT = '#93c5fd';
    const MAX_SPEED_KMH = 150;
    const DUPLICATE_METERS = 5;
    const PLAYBACK_SCALE = 60;

    const toNumber = (value) => {
        const parsed = Number.parseFloat(value);
        return Number.isFinite(parsed) ? parsed : null;
    };

    const toLatLng = (latitude, longitude) => {
        const lat = toNumber(latitude);
        const lng = toNumber(longitude);
        if (lat === null || lng === null) return null;
        return [lat, lng];
    };

    const parseTime = (value) => {
        if (!value) return null;
        const parsed = Date.parse(value);
        return Number.isFinite(parsed) ? parsed : null;
    };

    const formatClock = (ms) => {
        if (!Number.isFinite(ms)) return '—';
        return new Intl.DateTimeFormat('en-IN', {
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
            hour12: true,
            timeZone: 'Asia/Kolkata',
        }).format(new Date(ms));
    };

    const formatWhen = (value) => {
        const ms = typeof value === 'number' ? value : parseTime(value);
        if (ms === null) return '—';
        return new Intl.DateTimeFormat('en-IN', {
            day: '2-digit',
            month: 'short',
            hour: 'numeric',
            minute: '2-digit',
            second: '2-digit',
            hour12: true,
            timeZone: 'Asia/Kolkata',
        }).format(new Date(ms));
    };

    const haversine = (aLat, aLng, bLat, bLng) => {
        const earth = 6371000;
        const latDelta = (bLat - aLat) * Math.PI / 180;
        const lngDelta = (bLng - aLng) * Math.PI / 180;
        const h = Math.sin(latDelta / 2) ** 2
            + Math.cos(aLat * Math.PI / 180) * Math.cos(bLat * Math.PI / 180) * Math.sin(lngDelta / 2) ** 2;
        return earth * 2 * Math.atan2(Math.sqrt(h), Math.sqrt(1 - h));
    };

    const decimate = (latlngs, max = 800) => {
        if (latlngs.length <= max) return latlngs.slice();
        const step = Math.ceil((latlngs.length - 1) / (max - 1));
        const kept = [];
        for (let index = 0; index < latlngs.length - 1; index += step) kept.push(latlngs[index]);
        kept.push(latlngs[latlngs.length - 1]);
        return kept;
    };

    const waitForLeaflet = (attempt = 0) => {
        if (typeof window.L !== 'undefined') return Promise.resolve(window.L);
        if (attempt >= 40) return Promise.reject(new Error('Leaflet failed to load.'));
        return new Promise((resolve) => {
            window.setTimeout(() => resolve(waitForLeaflet(attempt + 1)), 100);
        });
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

    const statusClass = (status) => {
        if (status === 'moving' || status === 'stopped' || status === 'gps_signal_lost' || status === 'offline') {
            return status;
        }
        return 'offline';
    };

    function createPoller({ url, intervalFor, onData, onError }) {
        let timer = null;
        let stopped = true;
        let inFlight = false;
        let failures = 0;

        const delay = () => {
            const base = Math.max(4000, intervalFor());
            if (failures === 0) return base;
            return Math.min(60000, base * (2 ** failures));
        };

        const schedule = () => {
            if (stopped) return;
            window.clearTimeout(timer);
            timer = window.setTimeout(tick, delay());
        };

        async function tick() {
            if (stopped || inFlight || document.hidden) return;
            inFlight = true;
            try {
                const response = await fetch(url(), {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    cache: 'no-store',
                });
                if (!response.ok) throw new Error('Live feed failed');
                failures = 0;
                onData(await response.json());
            } catch (error) {
                failures = Math.min(failures + 1, 5);
                if (onError) onError(error);
            } finally {
                inFlight = false;
                schedule();
            }
        }

        const onVisible = () => {
            if (stopped || document.hidden) return;
            window.clearTimeout(timer);
            tick();
        };

        return {
            start() {
                if (!stopped) return;
                stopped = false;
                document.addEventListener('visibilitychange', onVisible);
                tick();
            },
            stop() {
                stopped = true;
                window.clearTimeout(timer);
                document.removeEventListener('visibilitychange', onVisible);
            },
        };
    }

    function preparePoints(rawPoints) {
        const points = (rawPoints || [])
            .map((point) => {
                const latLng = toLatLng(point.latitude, point.longitude);
                const time = parseTime(point.recorded_at);
                if (!latLng || time === null) return null;
                return {
                    id: point.id,
                    lat: latLng[0],
                    lng: latLng[1],
                    t: time,
                    speed: toNumber(point.speed),
                    recorded_at: point.recorded_at,
                };
            })
            .filter((point) => point !== null)
            .sort((a, b) => (a.t - b.t) || ((a.id || 0) - (b.id || 0)));

        const cumulative = [0];
        const segmentMeters = [0];

        for (let index = 1; index < points.length; index += 1) {
            const meters = haversine(points[index - 1].lat, points[index - 1].lng, points[index].lat, points[index].lng);
            const seconds = Math.max(1, (points[index].t - points[index - 1].t) / 1000);
            const kmh = (meters / 1000) / (seconds / 3600);
            const accepted = kmh <= MAX_SPEED_KMH ? meters : 0;
            segmentMeters[index] = accepted;
            cumulative[index] = cumulative[index - 1] + accepted;
        }

        return { points, cumulative, segmentMeters };
    }

    function mountRoute(config) {
        const mapElementId = config.mapElementId;
        const ui = document.getElementById(`${mapElementId}-ui`);
        const routeData = config.routeData || {};
        const prepared = preparePoints(routeData.valid_points && routeData.valid_points.length
            ? routeData.valid_points
            : (routeData.timeline || []));
        const stops = (routeData.stops || []).map((stop) => ({
            start: parseTime(stop.start_time),
            end: parseTime(stop.end_time),
            latitude: toNumber(stop.latitude),
            longitude: toNumber(stop.longitude),
        })).filter((stop) => stop.start !== null);

        const live = routeData.live || {};
        const sameAttendance = live.same_attendance === true;
        let mode = new URLSearchParams(window.location.search).get('mode') === 'live' ? 'live' : 'playback';
        let disposed = false;

        const state = {
            map: null,
            historical: null,
            played: null,
            playhead: null,
            liveLine: null,
            liveMarker: null,
            markerIndex: {},
            speed: 1,
            playing: false,
            paused: false,
            engaged: false,
            playheadMs: prepared.points[0]?.t ?? 0,
            raf: 0,
            lastFrame: 0,
            lineStamp: 0,
            cursorId: null,
            cursorTime: null,
            awaitingInitial: true,
            confirmed: [],
            queue: [],
            anim: null,
            follow: true,
            poller: null,
            placed: false,
            status: 'offline',
        };

        const uiQuery = (selector) => (ui ? ui.querySelector(selector) : null);

        const setText = (selector, value) => {
            const node = uiQuery(selector);
            if (node) node.textContent = value;
        };

        const numberIcon = (sequence, active = false) => window.L.divIcon({
            className: '',
            html: `<span class="er-number-marker${active ? ' is-active' : ''}">${sequence}</span>`,
            iconSize: [28, 28],
            iconAnchor: [14, 14],
            popupAnchor: [0, -14],
        });

        const endpointIcon = (kind) => window.L.divIcon({
            className: '',
            html: `<span class="er-endpoint-marker ${kind}">${kind === 'start' ? 'START' : 'END'}</span>`,
            iconSize: [56, 24],
            iconAnchor: [28, 12],
            popupAnchor: [0, -12],
        });

        const personIcon = (status) => window.L.divIcon({
            className: '',
            html: `<span class="er-live-dot ${statusClass(status)}"></span>`,
            iconSize: [22, 22],
            iconAnchor: [11, 11],
        });

        const playheadIcon = () => window.L.divIcon({
            className: '',
            html: '<span class="er-playhead"></span>',
            iconSize: [18, 18],
            iconAnchor: [9, 9],
        });

        const destroyMap = () => {
            const existing = window.__employeeRouteMapInstances?.[mapElementId];
            if (existing) {
                existing.remove();
                delete window.__employeeRouteMapInstances[mapElementId];
            }
            window.__employeeRouteMarkerIndex = window.__employeeRouteMarkerIndex || {};
            window.__employeeRouteMarkerIndex[mapElementId] = {};
        };

        const fitBounds = (bounds) => {
            if (!state.map || bounds.length === 0) return;
            const latLngBounds = window.L.latLngBounds(bounds);
            if (!latLngBounds.isValid()) return;
            state.map.fitBounds(latLngBounds, { padding: [48, 48], maxZoom: 16, animate: false });
        };

        const addChunkedLine = (latlngs, style) => {
            const group = window.L.layerGroup().addTo(state.map);
            if (latlngs.length < 2) return group;
            const size = 400;
            for (let index = 0; index < latlngs.length - 1; index += size) {
                const slice = latlngs.slice(index, Math.min(latlngs.length, index + size + 1));
                if (slice.length >= 2) window.L.polyline(slice, style).addTo(group);
            }
            return group;
        };

        const setHistoricalStyle = (faint) => {
            if (!state.historical) return;
            state.historical.eachLayer((layer) => {
                if (layer.setStyle) {
                    layer.setStyle({
                        color: faint ? BLUE_FAINT : BLUE,
                        opacity: faint ? 0.45 : 0.9,
                        weight: faint ? 4 : 5,
                    });
                }
            });
        };

        const sampleAt = (ms) => {
            const { points } = prepared;
            if (points.length === 0) return null;
            if (points.length === 1 || ms <= points[0].t) {
                return { lat: points[0].lat, lng: points[0].lng, index: 0, fraction: 0, t: points[0].t };
            }
            const last = points.length - 1;
            if (ms >= points[last].t) {
                return { lat: points[last].lat, lng: points[last].lng, index: last, fraction: 0, t: points[last].t };
            }
            let low = 0;
            let high = last;
            while (low < high - 1) {
                const mid = (low + high) >> 1;
                if (points[mid].t <= ms) low = mid;
                else high = mid;
            }
            const from = points[low];
            const to = points[high];
            const span = Math.max(1, to.t - from.t);
            const fraction = Math.min(1, Math.max(0, (ms - from.t) / span));
            return {
                lat: from.lat + (to.lat - from.lat) * fraction,
                lng: from.lng + (to.lng - from.lng) * fraction,
                index: low,
                fraction,
                t: ms,
            };
        };

        const distanceCoveredKm = (sample) => {
            if (!sample) return 0;
            const base = prepared.cumulative[sample.index] || 0;
            const extra = (prepared.segmentMeters[sample.index + 1] || 0) * (sample.fraction || 0);
            return (base + extra) / 1000;
        };

        const speedKmh = (sample) => {
            if (!sample) return 0;
            const { points } = prepared;
            const next = points[sample.index + 1];
            const current = points[sample.index];
            if (!next) {
                return current.speed !== null && current.speed >= 0 ? current.speed * 3.6 : 0;
            }
            const meters = prepared.segmentMeters[sample.index + 1] || 0;
            const seconds = Math.max(1, (next.t - current.t) / 1000);
            if (meters <= 0) return 0;
            return (meters / 1000) / (seconds / 3600);
        };

        const stoppageLabel = (ms) => {
            let seen = 0;
            let inside = null;
            stops.forEach((stop, index) => {
                if (stop.start !== null && ms >= stop.start) seen += 1;
                if (stop.start !== null && stop.end !== null && ms >= stop.start && ms <= stop.end) {
                    inside = index + 1;
                }
            });
            if (inside !== null) return `Stop ${inside} · ${seen} total`;
            return `${seen} stoppage${seen === 1 ? '' : 's'}`;
        };

        const renderPlaybackStats = (sample) => {
            if (!prepared.points.length) {
                setText('[data-er-stat="time"]', 'No GPS points');
                setText('[data-er-stat="distance"]', '0 km');
                setText('[data-er-stat="speed"]', '0 km/h');
                setText('[data-er-stat="stops"]', '0 stoppages');
                return;
            }
            const ms = sample?.t ?? state.playheadMs;
            setText('[data-er-stat="time"]', formatClock(ms));
            setText('[data-er-stat="distance"]', `${distanceCoveredKm(sample).toFixed(2)} km`);
            setText('[data-er-stat="speed"]', `${speedKmh(sample).toFixed(1)} km/h`);
            setText('[data-er-stat="stops"]', stoppageLabel(ms));
            const slider = uiQuery('[data-er-timeline]');
            const span = (prepared.points[prepared.points.length - 1].t - prepared.points[0].t) || 1;
            if (slider && document.activeElement !== slider) {
                slider.value = String(Math.round(((ms - prepared.points[0].t) / span) * 1000));
            }
        };

        const drawPlayed = (sample) => {
            if (!state.map || !sample) return;
            const prefix = prepared.points.slice(0, sample.index + 1).map((point) => [point.lat, point.lng]);
            prefix.push([sample.lat, sample.lng]);
            const draw = decimate(prefix);
            const now = performance.now();
            if (state.played && now - state.lineStamp < 80 && sample.fraction > 0 && sample.fraction < 1) {
                state.played.setLatLngs(draw);
                return;
            }
            state.lineStamp = now;
            if (!state.played) {
                state.played = window.L.polyline(draw, {
                    color: BLUE,
                    weight: 5,
                    opacity: 0.95,
                    lineJoin: 'round',
                    lineCap: 'round',
                }).addTo(state.map);
            } else {
                state.played.setLatLngs(draw);
            }
        };

        const renderPlayback = () => {
            if (mode !== 'playback' || !state.engaged) {
                renderPlaybackStats(sampleAt(state.playheadMs));
                return;
            }
            const sample = sampleAt(state.playheadMs);
            if (!sample || !state.map) return;
            if (!state.playhead) {
                state.playhead = window.L.marker([sample.lat, sample.lng], {
                    icon: playheadIcon(),
                    zIndexOffset: 1300,
                }).addTo(state.map);
            } else {
                state.playhead.setLatLng([sample.lat, sample.lng]);
            }
            drawPlayed(sample);
            setHistoricalStyle(true);
            renderPlaybackStats(sample);
            if (state.map.getBounds && !state.map.getBounds().contains([sample.lat, sample.lng])) {
                state.map.panTo([sample.lat, sample.lng], { animate: true });
            }
        };

        const stopRaf = () => {
            if (state.raf) window.cancelAnimationFrame(state.raf);
            state.raf = 0;
        };

        const tick = (now) => {
            if (!state.playing || disposed || mode !== 'playback') return;
            const delta = Math.min(100, now - (state.lastFrame || now));
            state.lastFrame = now;
            const start = prepared.points[0]?.t ?? 0;
            const end = prepared.points[prepared.points.length - 1]?.t ?? start;
            if (end - start < 1000) {
                const step = ((end - start) || 8000) * (delta / 8000) * state.speed;
                state.playheadMs += step || (delta * state.speed);
            } else {
                state.playheadMs += delta * state.speed * PLAYBACK_SCALE;
            }
            if (state.playheadMs >= end) {
                state.playheadMs = end;
                state.playing = false;
                state.paused = false;
                renderPlayback();
                syncButtons();
                return;
            }
            renderPlayback();
            state.raf = window.requestAnimationFrame(tick);
        };

        const syncButtons = () => {
            const canPlay = prepared.points.length >= 2 && mode === 'playback';
            const play = uiQuery('[data-er-play]');
            const pause = uiQuery('[data-er-pause]');
            const resume = uiQuery('[data-er-resume]');
            const restart = uiQuery('[data-er-restart]');
            const stop = uiQuery('[data-er-stop]');
            const slider = uiQuery('[data-er-timeline]');
            if (play) play.disabled = !canPlay || state.playing;
            if (pause) pause.disabled = !state.playing;
            if (resume) resume.disabled = !state.paused;
            if (restart) restart.disabled = !canPlay;
            if (stop) stop.disabled = !canPlay || (!state.playing && !state.paused && !state.engaged);
            if (slider) slider.disabled = !canPlay;
            ui?.querySelectorAll('[data-er-speed]').forEach((button) => {
                button.classList.toggle('is-active', Number(button.dataset.erSpeed) === state.speed);
                button.disabled = mode !== 'playback';
            });
            ui?.querySelectorAll('[data-er-mode]').forEach((button) => {
                const active = button.dataset.erMode === mode;
                button.classList.toggle('is-active', active);
                button.setAttribute('aria-pressed', active ? 'true' : 'false');
            });
            const playbackPanel = uiQuery('[data-er-panel="playback"]');
            const livePanel = uiQuery('[data-er-panel="live"]');
            if (playbackPanel) playbackPanel.hidden = mode !== 'playback';
            if (livePanel) livePanel.hidden = mode !== 'live';
        };

        const play = () => {
            if (prepared.points.length < 2) return;
            const end = prepared.points[prepared.points.length - 1].t;
            if (state.playheadMs >= end) state.playheadMs = prepared.points[0].t;
            state.engaged = true;
            state.playing = true;
            state.paused = false;
            state.lastFrame = performance.now();
            stopRaf();
            state.raf = window.requestAnimationFrame(tick);
            syncButtons();
        };

        const pause = () => {
            state.playing = false;
            state.paused = state.engaged;
            stopRaf();
            syncButtons();
        };

        const resume = () => {
            if (!state.paused) return;
            play();
        };

        const restart = () => {
            if (!prepared.points.length) return;
            state.playheadMs = prepared.points[0].t;
            state.engaged = true;
            state.playing = true;
            state.paused = false;
            state.lastFrame = performance.now();
            stopRaf();
            renderPlayback();
            state.raf = window.requestAnimationFrame(tick);
            syncButtons();
        };

        const stop = () => {
            stopRaf();
            state.playing = false;
            state.paused = false;
            state.engaged = false;
            state.playheadMs = prepared.points[0]?.t ?? 0;
            if (state.playhead) {
                state.map?.removeLayer(state.playhead);
                state.playhead = null;
            }
            if (state.played) {
                state.map?.removeLayer(state.played);
                state.played = null;
            }
            setHistoricalStyle(false);
            renderPlaybackStats(sampleAt(state.playheadMs));
            const slider = uiQuery('[data-er-timeline]');
            if (slider) slider.value = '0';
            syncButtons();
        };

        const seek = (ms, options = {}) => {
            if (!prepared.points.length) return;
            const start = prepared.points[0].t;
            const end = prepared.points[prepared.points.length - 1].t;
            state.playheadMs = Math.min(end, Math.max(start, ms));
            state.engaged = true;
            if (options.pause) {
                state.playing = false;
                state.paused = true;
                stopRaf();
            }
            setHistoricalStyle(true);
            renderPlayback();
            syncButtons();
        };

        const setActiveMarker = (eventId) => {
            Object.entries(state.markerIndex).forEach(([id, entry]) => {
                if (!entry?.marker) return;
                const active = id === eventId;
                if (entry.kind === 'stoppage') {
                    entry.marker.setIcon(numberIcon(entry.sequence, active));
                    entry.marker.setZIndexOffset(active ? 1000 : 500);
                } else {
                    entry.marker.setZIndexOffset(active ? 1100 : 700);
                }
                if (active) entry.marker.openPopup();
            });
        };

        const focusEvent = (eventId) => {
            const entry = state.markerIndex[eventId];
            if (!state.map || !entry?.marker) return;
            setActiveMarker(eventId);
            state.map.panTo(entry.marker.getLatLng(), { animate: true });
            if (state.map.getZoom() < 14) state.map.setZoom(15, { animate: true });
            const journey = (routeData.journey_events || []).find((event) => event.id === eventId);
            const when = parseTime(journey?.start_time);
            if (when !== null && mode === 'playback') seek(when, { pause: true });
        };

        const drawHistorical = () => {
            const bounds = [];
            const pushBound = (latLng) => {
                if (latLng) bounds.push(latLng);
            };
            const latlngs = prepared.points.map((point) => [point.lat, point.lng]);
            latlngs.forEach(pushBound);
            if (latlngs.length >= 2) {
                state.historical = addChunkedLine(decimate(latlngs, 1200), {
                    color: BLUE,
                    weight: 5,
                    opacity: 0.9,
                    lineJoin: 'round',
                    lineCap: 'round',
                });
            }

            const register = (id, kind, sequence, marker) => {
                state.markerIndex[id] = { kind, sequence, marker };
                marker.on('click', () => {
                    window.dispatchEvent(new CustomEvent('er-marker-selected', { detail: { id } }));
                    focusEvent(id);
                });
            };

            (routeData.journey_events || []).forEach((event) => {
                if (event.type === 'travel') return;
                const latLng = toLatLng(event.latitude, event.longitude);
                if (!latLng) return;
                pushBound(latLng);
                if (event.type === 'start' || event.type === 'end') {
                    const marker = window.L.marker(latLng, { icon: endpointIcon(event.type), zIndexOffset: 700 }).addTo(state.map);
                    marker.bindPopup(`<strong>${event.label || event.type}</strong><br>${event.time_label || ''}<br>${event.location || ''}`);
                    register(event.id, event.type, null, marker);
                    return;
                }
                if (event.type === 'stoppage') {
                    const marker = window.L.marker(latLng, { icon: numberIcon(event.sequence, false), zIndexOffset: 500 }).addTo(state.map);
                    marker.bindPopup(`<strong>Stop ${event.sequence}</strong><br>${event.time_label || ''}<br>${event.duration_label || ''}`);
                    register(event.id, 'stoppage', event.sequence, marker);
                }
            });

            const punchIn = routeData.punch_in || {};
            const punchOut = routeData.punch_out || {};
            if (!state.markerIndex.start) {
                const pin = toLatLng(punchIn.latitude, punchIn.longitude);
                if (pin) {
                    pushBound(pin);
                    const marker = window.L.marker(pin, { icon: endpointIcon('start'), zIndexOffset: 700 }).addTo(state.map);
                    marker.bindPopup(`<strong>START</strong><br>${punchIn.time || ''}`);
                    register('start', 'start', null, marker);
                }
            }
            if (!state.markerIndex.end) {
                const pout = toLatLng(punchOut.latitude, punchOut.longitude);
                if (pout) {
                    pushBound(pout);
                    const marker = window.L.marker(pout, { icon: endpointIcon('end'), zIndexOffset: 700 }).addTo(state.map);
                    marker.bindPopup(`<strong>END</strong><br>${punchOut.time || ''}`);
                    register('end', 'end', null, marker);
                }
            }

            if (bounds.length > 0) fitBounds(bounds);
            else state.map.setView([20.5937, 78.9629], 5);

            window.__employeeRouteMarkerIndex[mapElementId] = state.markerIndex;
            return bounds;
        };

        const updateLiveCard = (data) => {
            const employee = data?.employee || routeData.employee || {};
            setText('[data-er-live="name"]', employee.full_name || 'Employee');
            const statusNode = uiQuery('[data-er-live="status"]');
            if (statusNode) {
                statusNode.textContent = data?.status_label || 'Offline';
                statusNode.className = `er-status ${statusClass(data?.status)}`;
            }
            state.status = data?.status || state.status;
            const speedLabel = data?.status === 'moving' ? 'Current speed' : 'Last speed';
            const speed = data?.speed_kmh === null || data?.speed_kmh === undefined ? '—' : `${data.speed_kmh} km/h`;
            setText('[data-er-live="speed"]', `${speedLabel}: ${speed}`);
            setText('[data-er-live="updated"]', `Last GPS update: ${formatWhen(data?.last_gps_at)}`);
            const photo = uiQuery('[data-er-live="photo"]');
            if (photo) {
                if (employee.photo_url) {
                    photo.src = employee.photo_url;
                    photo.hidden = false;
                    photo.alt = employee.full_name || 'Employee photo';
                } else {
                    photo.removeAttribute('src');
                    photo.hidden = true;
                }
            }
            const note = uiQuery('[data-er-live="note"]');
            if (note) {
                note.textContent = live.same_attendance
                    ? ''
                    : (data?.punched_in ? "Showing today's live session." : 'No active trip. The marker stays on the last stored fix.');
            }
            if (state.liveMarker) state.liveMarker.setIcon(personIcon(state.status));
        };

        const setLiveLine = (latlngs) => {
            const draw = decimate(latlngs);
            if (!state.liveLine) {
                state.liveLine = window.L.polyline(draw, {
                    color: BLUE,
                    weight: 5,
                    opacity: 0.95,
                    lineJoin: 'round',
                    lineCap: 'round',
                }).addTo(state.map);
                return;
            }
            state.liveLine.setLatLngs(draw);
        };

        const placeLive = (lat, lng, pan) => {
            if (!state.liveMarker) {
                state.liveMarker = window.L.marker([lat, lng], {
                    icon: personIcon(state.status),
                    zIndexOffset: 1400,
                }).addTo(state.map);
            } else {
                state.liveMarker.setLatLng([lat, lng]);
            }
            state.placed = true;
            if (pan && state.follow) state.map.panTo([lat, lng], { animate: false });
        };

        const pumpLive = () => {
            if (state.anim || state.queue.length === 0 || mode !== 'live') return;
            const next = state.queue.shift();
            if (!state.placed || !state.liveMarker) {
                state.confirmed.push([next.latitude, next.longitude]);
                setLiveLine(state.confirmed);
                placeLive(next.latitude, next.longitude, true);
                pumpLive();
                return;
            }
            const from = state.liveMarker.getLatLng();
            const to = window.L.latLng(next.latitude, next.longitude);
            if (haversine(from.lat, from.lng, to.lat, to.lng) <= DUPLICATE_METERS) {
                pumpLive();
                return;
            }
            state.anim = {
                from,
                to,
                started: performance.now(),
                duration: state.queue.length > 2 ? 450 : 1800,
            };
            const frame = (now) => {
                if (!state.anim || disposed || mode !== 'live') return;
                const t = Math.min(1, (now - state.anim.started) / state.anim.duration);
                const eased = t * t * (3 - 2 * t);
                const lat = state.anim.from.lat + (state.anim.to.lat - state.anim.from.lat) * eased;
                const lng = state.anim.from.lng + (state.anim.to.lng - state.anim.from.lng) * eased;
                state.liveMarker.setLatLng([lat, lng]);
                setLiveLine(state.confirmed.concat([[lat, lng]]));
                if (state.follow) state.map.panTo([lat, lng], { animate: false });
                if (t < 1) {
                    state.anim.raf = window.requestAnimationFrame(frame);
                    return;
                }
                state.confirmed.push([state.anim.to.lat, state.anim.to.lng]);
                setLiveLine(state.confirmed);
                state.anim = null;
                pumpLive();
            };
            state.anim.raf = window.requestAnimationFrame(frame);
        };

        const applyFeed = (data) => {
            if (disposed || mode !== 'live') return;
            updateLiveCard(data);
            const connection = uiQuery('[data-er-live="connection"]');
            if (connection) connection.textContent = '';

            if (data.initial && state.awaitingInitial) {
                const points = data.points || [];
                state.confirmed = points
                    .map((point) => toLatLng(point.latitude, point.longitude))
                    .filter((latLng) => latLng !== null);
                if (state.confirmed.length) setLiveLine(state.confirmed);
                const last = points[points.length - 1];
                if (last) placeLive(Number(last.latitude), Number(last.longitude), true);
                else if (data.latitude !== null && data.longitude !== null) {
                    placeLive(Number(data.latitude), Number(data.longitude), true);
                }
                state.awaitingInitial = false;
            } else {
                (data.points || []).forEach((point) => {
                    if (point.animate !== true) return;
                    const recorded = parseTime(point.recorded_at);
                    const cursorMs = parseTime(state.cursorTime);
                    if (cursorMs !== null && recorded !== null && recorded < cursorMs) return;
                    if (state.cursorId && point.id <= state.cursorId && data.initial !== true) return;
                    state.queue.push(point);
                });
                pumpLive();
            }

            if (data.cursor_id) state.cursorId = data.cursor_id;
            if (data.last_gps_at) state.cursorTime = data.last_gps_at;
        };

        const seedLiveFromHistory = () => {
            if (!sameAttendance || prepared.points.length === 0) return false;
            state.confirmed = prepared.points.map((point) => [point.lat, point.lng]);
            setLiveLine(state.confirmed);
            const last = prepared.points[prepared.points.length - 1];
            placeLive(last.lat, last.lng, true);
            state.cursorId = last.id || null;
            state.cursorTime = last.recorded_at || null;
            state.awaitingInitial = false;
            state.placed = true;
            return true;
        };

        const enterLive = () => {
            mode = 'live';
            pause();
            if (state.playhead) state.playhead.setOpacity(0);
            setHistoricalStyle(true);
            if (state.historical) {
                state.historical.eachLayer((layer) => {
                    if (layer.setStyle) layer.setStyle({ opacity: 0 });
                });
            }
            if (!seedLiveFromHistory() && state.awaitingInitial === false && state.confirmed.length) {
                setLiveLine(state.confirmed);
            }
            updateLiveCard({
                employee: routeData.employee,
                status: 'offline',
                status_label: 'Checking…',
                speed_kmh: null,
                last_gps_at: null,
                punched_in: live.tracking_active === true,
            });
            if (!state.poller && live.feed_url) {
                state.poller = createPoller({
                    url: () => {
                        const endpoint = new URL(live.feed_url, window.location.origin);
                        if (state.cursorId) endpoint.searchParams.set('after_id', String(state.cursorId));
                        if (state.cursorTime) endpoint.searchParams.set('after_recorded_at', state.cursorTime);
                        return endpoint.toString();
                    },
                    intervalFor: () => {
                        if (state.status === 'moving') return 8000;
                        if (state.status === 'stopped') return 12000;
                        if (state.status === 'gps_signal_lost') return 20000;
                        return 30000;
                    },
                    onData: applyFeed,
                    onError: () => {
                        const connection = uiQuery('[data-er-live="connection"]');
                        if (connection) connection.textContent = 'Connection interrupted. The marker stays on the last confirmed fix.';
                    },
                });
            }
            state.poller?.start();
            syncButtons();
            window.setTimeout(() => state.map?.invalidateSize({ animate: false, pan: false }), 60);
        };

        const exitLive = () => {
            mode = 'playback';
            state.poller?.stop();
            if (state.anim?.raf) window.cancelAnimationFrame(state.anim.raf);
            state.anim = null;
            state.queue = [];
            if (state.liveMarker) {
                state.map?.removeLayer(state.liveMarker);
                state.liveMarker = null;
            }
            if (state.liveLine) {
                state.map?.removeLayer(state.liveLine);
                state.liveLine = null;
            }
            state.placed = false;
            if (state.playhead) state.playhead.setOpacity(1);
            setHistoricalStyle(state.engaged);
            if (!state.engaged && state.historical) {
                state.historical.eachLayer((layer) => {
                    if (layer.setStyle) layer.setStyle({ color: BLUE, opacity: 0.9, weight: 5 });
                });
            }
            syncButtons();
            window.setTimeout(() => state.map?.invalidateSize({ animate: false, pan: false }), 60);
        };

        const bindUi = () => {
            if (!ui) return;
            if (ui._erAbort) ui._erAbort.abort();
            const abort = new AbortController();
            ui._erAbort = abort;
            const signal = abort.signal;
            const on = (selector, eventName, handler) => {
                ui.querySelectorAll(selector).forEach((node) => node.addEventListener(eventName, handler, { signal }));
            };
            on('[data-er-mode]', 'click', (event) => {
                const next = event.currentTarget.dataset.erMode;
                if (next === 'live') enterLive();
                else exitLive();
            });
            on('[data-er-play]', 'click', play);
            on('[data-er-pause]', 'click', pause);
            on('[data-er-resume]', 'click', resume);
            on('[data-er-restart]', 'click', restart);
            on('[data-er-stop]', 'click', stop);
            on('[data-er-speed]', 'click', (event) => {
                state.speed = Number(event.currentTarget.dataset.erSpeed) || 1;
                syncButtons();
            });
            on('[data-er-timeline]', 'input', (event) => {
                if (!prepared.points.length) return;
                const ratio = Number(event.currentTarget.value) / 1000;
                const start = prepared.points[0].t;
                const end = prepared.points[prepared.points.length - 1].t;
                seek(start + (end - start) * ratio, { pause: !state.playing });
            });
            on('[data-er-follow]', 'click', () => {
                state.follow = true;
                const button = uiQuery('[data-er-follow]');
                if (button) button.textContent = 'Following';
                if (state.liveMarker) state.map.panTo(state.liveMarker.getLatLng(), { animate: true });
            });
        };

        const renderMap = () => {
            const mapElement = document.getElementById(mapElementId);
            if (!mapElement || mapElement.offsetWidth < 40 || mapElement.offsetHeight < 40) return false;
            destroyMap();
            state.map = window.L.map(mapElement, { preferCanvas: true, zoomControl: true });
            window.__employeeRouteMapInstances[mapElementId] = state.map;
            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(state.map);
            const bounds = drawHistorical();
            state.map.on('dragstart', () => {
                if (mode !== 'live') return;
                state.follow = false;
                const button = uiQuery('[data-er-follow]');
                if (button) button.textContent = 'Follow';
            });
            window.requestAnimationFrame(() => {
                state.map.invalidateSize({ animate: false, pan: false });
                fitBounds(bounds);
            });
            return true;
        };

        const selectHandler = (event) => {
            if (disposed) return;
            focusEvent(event.detail?.id);
        };

        const start = () => {
            window.__employeeRouteMapInstances = window.__employeeRouteMapInstances || {};
            window.__employeeRouteMarkerIndex = window.__employeeRouteMarkerIndex || {};
            if (window.__erSelectHandler) window.removeEventListener('er-select-event', window.__erSelectHandler);
            window.__erSelectHandler = selectHandler;
            window.addEventListener('er-select-event', selectHandler);
            bindUi();
            const boot = () => {
                if (disposed) return;
                if (!renderMap()) {
                    window.setTimeout(boot, 150);
                    return;
                }
                renderPlaybackStats(sampleAt(prepared.points[0]?.t ?? 0));
                syncButtons();
                if (mode === 'live') enterLive();
            };
            waitForLeaflet().then(boot).catch((error) => console.error('[EmployeeRouteMap]', error));
        };

        start();

        const resize = () => state.map?.invalidateSize({ animate: false, pan: false });
        window.addEventListener('resize', resize);
        const mapElement = document.getElementById(mapElementId);
        let observer = null;
        if (mapElement && typeof ResizeObserver !== 'undefined') {
            observer = new ResizeObserver(() => resize());
            observer.observe(mapElement);
        }

        return () => {
            disposed = true;
            stopRaf();
            state.poller?.stop();
            if (state.anim?.raf) window.cancelAnimationFrame(state.anim.raf);
            if (ui?._erAbort) ui._erAbort.abort();
            window.removeEventListener('resize', resize);
            window.removeEventListener('er-select-event', selectHandler);
            observer?.disconnect();
            destroyMap();
        };
    }

    function mountOverview(config) {
        const mapElementId = config.mapElementId;
        const list = document.getElementById(config.listElementId);
        const tracks = new Map();
        let map = null;
        let disposed = false;
        let followId = null;
        let anyoneMoving = false;

        const markerIcon = (employee) => {
            const letter = escapeHtml((employee.full_name || '?').trim().charAt(0).toUpperCase() || '?');
            return window.L.divIcon({
                className: '',
                html: `<span class="lv-marker ${statusClass(employee.status)}"><b>${letter}</b><span>${escapeHtml(employee.full_name || '')}</span></span>`,
                iconSize: [120, 36],
                iconAnchor: [16, 18],
            });
        };

        const renderList = (employees) => {
            if (!list) return;
            if (!employees.length) {
                list.innerHTML = '<p class="lv-empty">No employees are punched in right now.</p>';
                return;
            }
            list.innerHTML = employees.map((employee) => `
                <a class="lv-person" href="${escapeHtml(employee.route_url)}">
                    <span class="lv-badge ${statusClass(employee.status)}">${escapeHtml((employee.full_name || '?').charAt(0))}</span>
                    <span>
                        <strong>${escapeHtml(employee.full_name || 'Employee')}</strong>
                        <small>${escapeHtml(employee.status_label || 'Offline')}${employee.speed_kmh != null ? ` · ${escapeHtml(employee.speed_kmh)} km/h` : ''}</small>
                    </span>
                </a>
            `).join('');
        };

        const apply = (data) => {
            if (!map || disposed) return;
            const employees = data.employees || [];
            anyoneMoving = employees.some((employee) => employee.status === 'moving');
            renderList(employees);
            const seen = new Set();
            employees.forEach((employee) => {
                const key = String(employee.employee?.id || employee.id || employee.attendance_id);
                seen.add(key);
                const lat = toNumber(employee.latitude);
                const lng = toNumber(employee.longitude);
                if (lat === null || lng === null) return;
                const recorded = parseTime(employee.last_gps_at);
                let track = tracks.get(key);
                if (!track) {
                    const marker = window.L.marker([lat, lng], { icon: markerIcon(employee), zIndexOffset: 600 }).addTo(map);
                    marker.on('click', () => {
                        if (employee.route_url) window.location.href = employee.route_url;
                    });
                    track = { marker, pointId: employee.point_id, recorded, lat, lng, anim: null };
                    tracks.set(key, track);
                    return;
                }
                track.marker.setIcon(markerIcon(employee));
                const samePoint = employee.point_id && track.pointId && employee.point_id === track.pointId;
                const older = recorded !== null && track.recorded !== null && recorded < track.recorded;
                const meters = haversine(track.lat, track.lng, lat, lng);
                if (samePoint || older || meters <= DUPLICATE_METERS) {
                    if (employee.point_id) track.pointId = employee.point_id;
                    return;
                }
                const from = window.L.latLng(track.lat, track.lng);
                const to = window.L.latLng(lat, lng);
                const started = performance.now();
                const token = {};
                track.anim = token;
                track.pointId = employee.point_id;
                track.recorded = recorded;
                const frame = (now) => {
                    if (disposed || track.anim !== token) return;
                    const t = Math.min(1, (now - started) / 1600);
                    const eased = t * t * (3 - 2 * t);
                    const nextLat = from.lat + (to.lat - from.lat) * eased;
                    const nextLng = from.lng + (to.lng - from.lng) * eased;
                    track.marker.setLatLng([nextLat, nextLng]);
                    if (t < 1) {
                        window.requestAnimationFrame(frame);
                        return;
                    }
                    track.lat = lat;
                    track.lng = lng;
                    track.anim = null;
                };
                window.requestAnimationFrame(frame);
            });
            tracks.forEach((track, key) => {
                if (!seen.has(key)) {
                    map.removeLayer(track.marker);
                    tracks.delete(key);
                }
            });
            if (tracks.size && !followId) {
                const bounds = [];
                tracks.forEach((track) => bounds.push([track.lat, track.lng]));
                if (bounds.length === 1) map.setView(bounds[0], 15);
                else map.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
                followId = 'fitted';
            }
        };

        const poller = createPoller({
            url: () => config.overviewUrl,
            intervalFor: () => (anyoneMoving ? 8000 : 15000),
            onData: apply,
            onError: () => {
                if (list && !list.children.length) {
                    list.innerHTML = '<p class="lv-empty">Live positions are unavailable right now.</p>';
                }
            },
        });

        const boot = () => {
            const element = document.getElementById(mapElementId);
            if (!element || element.offsetWidth < 40 || element.offsetHeight < 40) {
                window.setTimeout(boot, 150);
                return;
            }
            map = window.L.map(element, { preferCanvas: true, zoomControl: true });
            window.L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            }).addTo(map);
            map.setView([20.5937, 78.9629], 5);
            poller.start();
            window.setTimeout(() => map.invalidateSize({ animate: false, pan: false }), 80);
        };

        waitForLeaflet().then(boot).catch((error) => console.error('[EmployeeRouteOverview]', error));

        return () => {
            disposed = true;
            poller.stop();
            map?.remove();
        };
    }

    window.EmployeeRouteTracking = { mountRoute, mountOverview };
})();
