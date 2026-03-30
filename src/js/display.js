var timeBack = ["", "", "", "", "", "", ""];
var shipBack = ["", "", "", "", "", "", ""];
var stationBack = ["", "", "", "", "", "", ""];
var soldoutBack = ["", "", "", "", "", "", ""];
var statusBack = ["", "", "", "", "", "", ""];
var detailBack = ["", "", "", "", "", "", ""];
var badgeBack = ["", "", "", "", "", "", ""];
var messageBack = "";
var contentBackSignature = "";
var contentBackIntervalSeconds = null;
var notifyBackSignature = "";
var guidanceBackSignature = "";
var status = 0;
var displayRotationTimer = null;
var currentContentIndex = 0;
var currentDisplayIndex = 0;
var displaySequence = [];
var DEFAULT_DISPLAY_SWAP_INTERVAL_SECONDS = 8;
var tickerAnimationFrame = null;
var tickerMessages = [];
var tickerSpeed = 4;
var tickerIndex = 0;
var tickerX = 0;
var tickerLastTs = 0;
var latestTimetableRows = [];
var currentContentItems = [];
var currentContentSwapInterval = DEFAULT_DISPLAY_SWAP_INTERVAL_SECONDS;
var guidanceConfig = { ready: false, lead_minutes: 0, items: [], updated_at: "" };
var guidancePlaying = false;
var guidanceQueue = [];
var guidanceCurrentIndex = 0;
var guidanceSoundTimeout = null;
var lastGuidanceTriggerKey = "";
var DISPLAY_LANGUAGE_SWAP_INTERVAL_MS = 5 * 1000;
var latestTickerMessages = [];
var latestTickerSpeed = 4;

$(document).ready(function () {
    var stationId = $("#station").text();
    var mode = parseInt($("#mode").text(), 10) || 0;
    var englishSwapEnabled = $("#english_swap_enabled").text() === "1";
    var currentLanguage = englishSwapEnabled ? "en" : "jp";
    var languageSwapTimer = null;
    var languageSwapRemainingMs = DISPLAY_LANGUAGE_SWAP_INTERVAL_MS;
    var languageSwapStartedAt = 0;
    var displayShellEl = document.getElementById("displayShell");
    var displayTitleEl = document.getElementById("displayTitle");
    var clockLabelEl = document.getElementById("clockLabel");
    var headerTimeEl = document.getElementById("headerTime");
    var headerShipEl = document.getElementById("headerShip");
    var headerDestinationEl = document.getElementById("headerDestination");
    var headerStatusEl = document.getElementById("headerStatus");
    var tableStageEl = document.getElementById("tableStage");
    var contentStagePanelEl = document.getElementById("contentStagePanel");
    var contentSlidesEl = document.getElementById("contentStageSlides");
    var contentEmptyEl = document.getElementById("contentStageEmpty");
    var contentIndicatorEl = document.getElementById("contentStageIndicator");
    var contentTitleEl = document.getElementById("contentStageTitle");
    var notifyPanelEl = document.getElementById("notifyPanel");
    var notifyImageEl = document.getElementById("notifyImage");
    var notifyLabelEl = document.getElementById("notifyLabel");
    var notifyTitleEl = document.getElementById("notifyTitle");
    var guidancePanelEl = document.getElementById("guidancePanel");
    var guidanceVideoEl = document.getElementById("guidanceVideo");
    var guidanceLabelEl = document.getElementById("guidanceLabel");
    var guidanceTitleEl = document.getElementById("guidanceTitle");
    var tickerWrapEl = document.querySelector(".ticker-wrap");
    var tickerMessageEl = document.getElementById("dsp_message");

    function updateClock() {
        var now = new Date();
        var hh = String(now.getHours()).padStart(2, "0");
        var mm = String(now.getMinutes()).padStart(2, "0");
        $("#current_time").text(hh + " : " + mm);
    }

    function isEnglishActive() {
        return englishSwapEnabled && currentLanguage === "en";
    }

    function selectLanguageValue(japanese, english) {
        var jp = String(japanese || "").trim();
        var en = String(english || "").trim();
        if (isEnglishActive()) {
            return en || jp;
        }
        return jp || en;
    }

    function selectSecondaryValue(japanese, english) {
        if (englishSwapEnabled) {
            return "";
        }

        var primary = selectLanguageValue(japanese, english);
        var secondary = String(english || "").trim();
        if (secondary === primary) {
            return "";
        }
        return secondary;
    }

    function labelByLanguage(japanese, english) {
        return isEnglishActive() ? english : japanese;
    }

    function renderStaticLabels() {
        var stationName = selectLanguageValue(
            displayTitleEl ? displayTitleEl.getAttribute("data-station-jp") : "",
            displayTitleEl ? displayTitleEl.getAttribute("data-station-en") : ""
        );

        if (displayTitleEl) {
            displayTitleEl.textContent = isEnglishActive()
                ? ("Timetable " + stationName + " Departures")
                : ("時刻表　" + stationName + "発");
        }
        if (clockLabelEl) {
            clockLabelEl.textContent = labelByLanguage("現在時刻", "Current Time");
        }
        if (headerTimeEl) {
            headerTimeEl.textContent = labelByLanguage("時刻", "Time");
        }
        if (headerShipEl) {
            headerShipEl.textContent = labelByLanguage("艇名", "Vessel");
        }
        if (headerDestinationEl) {
            headerDestinationEl.textContent = labelByLanguage("行き先", "Destination");
        }
        if (headerStatusEl) {
            headerStatusEl.textContent = labelByLanguage("乗船案内", "Boarding");
        }
    }

    function playSound() {
        if (mode !== 0) {
            return;
        }
        var audio = new Audio();
        audio.src = "img/sound.mp3";
        setTimeout(function () {
            audio.play();
        }, 1000);
    }

    function normalizeMessageSpeed(value) {
        var speed = parseInt(value, 10);
        if (isNaN(speed)) {
            return 80;
        }
        if (speed <= 10) {
            if (speed < 1) {
                speed = 1;
            }
            return 20 + ((speed - 1) * 20);
        }
        if (speed < 20) {
            return 20;
        }
        if (speed > 300) {
            return 300;
        }
        return speed;
    }

    function stopTicker() {
        if (tickerAnimationFrame) {
            window.cancelAnimationFrame(tickerAnimationFrame);
            tickerAnimationFrame = null;
        }
        tickerLastTs = 0;
    }

    function messageSignature(messages, dragSpeed) {
        return JSON.stringify([
            parseInt(dragSpeed, 10) || 0,
            (messages || []).map(function (message) {
                return [
                    message.message_id || 0,
                    message.message || "",
                    message.sort_order || 0
                ];
            })
        ]);
    }

    function normalizeTickerMessages(messages) {
        return (messages || []).map(function (message) {
            return {
                message_id: message ? message.message_id || 0 : 0,
                message: selectLanguageValue(
                    message ? message.message : "",
                    message ? message.message_e : ""
                ),
                sort_order: message ? message.sort_order || 0 : 0
            };
        }).filter(function (message) {
            return String(message.message || "").trim() !== "";
        });
    }

    function startTicker() {
        stopTicker();

        if (!tickerWrapEl || !tickerMessageEl || !tickerMessages.length) {
            if (tickerMessageEl) {
                tickerMessageEl.textContent = "";
                tickerMessageEl.style.transform = "translateX(0px)";
            }
            return;
        }

        tickerIndex = 0;
        tickerX = tickerWrapEl.clientWidth;
        tickerLastTs = 0;
        tickerMessageEl.textContent = tickerMessages[tickerIndex].message || "";
        tickerMessageEl.style.transform = "translateX(" + tickerX + "px)";
        tickerAnimationFrame = window.requestAnimationFrame(stepTicker);
    }

    function stepTicker(timestamp) {
        if (!tickerWrapEl || !tickerMessageEl || !tickerMessages.length) {
            stopTicker();
            return;
        }

        if (!tickerLastTs) {
            tickerLastTs = timestamp;
        }

        var dt = (timestamp - tickerLastTs) / 1000;
        tickerLastTs = timestamp;
        tickerX -= normalizeMessageSpeed(tickerSpeed) * dt;
        tickerMessageEl.style.transform = "translateX(" + tickerX + "px)";

        if (tickerX + tickerMessageEl.scrollWidth < 0) {
            tickerIndex += 1;
            if (tickerIndex >= tickerMessages.length) {
                tickerIndex = 0;
            }
            tickerMessageEl.textContent = tickerMessages[tickerIndex].message || "";
            tickerX = tickerWrapEl.clientWidth;
            tickerLastTs = timestamp;
            tickerMessageEl.style.transform = "translateX(" + tickerX + "px)";
        }

        tickerAnimationFrame = window.requestAnimationFrame(stepTicker);
    }

    function renderTicker(messages, dragSpeed, silent) {
        var normalizedMessages = normalizeTickerMessages(messages);
        var signature = messageSignature(normalizedMessages, dragSpeed);
        if (signature === messageBack) {
            return;
        }
        if (signature !== messageBack && !silent) {
            playSound();
        }
        messageBack = signature;
        tickerMessages = normalizedMessages;
        tickerSpeed = parseInt(dragSpeed, 10) || 4;
        startTicker();
    }

    function renderTimetableRows(rows) {
        var data = Array.isArray(rows) ? rows : [];
        var maxRows = $(".col_time").length;
        var cnt = 0;

        for (cnt = 0; cnt < data.length; cnt++) {
            var row = data[cnt] || {};
            var time = String(row.time || "").substr(0, 5);
            var ship = selectLanguageValue(row.ship, row.shipe);
            var station = selectLanguageValue(row.station, row.statione);
            var stationSecondary = selectSecondaryValue(row.station, row.statione);
            var badgeLabel = selectLanguageValue(row.badge_label, row.badge_label_e);

            $(".col_time").eq(cnt).text(time);
            $(".col_ship").eq(cnt).text(ship);
            $(".destination").eq(cnt).text(station);
            $(".destinatione").eq(cnt).text(stationSecondary);
            $(".col_badge_float").eq(cnt).text(badgeLabel);
            $(".col_badge_float").eq(cnt).toggle(!!badgeLabel);
            $(".col_status").eq(cnt).html(renderStatusCell(row));
        }

        for (; cnt < maxRows; cnt++) {
            $(".col_time").eq(cnt).text("");
            $(".col_ship").eq(cnt).text("");
            $(".destination").eq(cnt).text("");
            $(".destinatione").eq(cnt).text("");
            $(".col_badge_float").eq(cnt).text("");
            $(".col_badge_float").eq(cnt).hide();
            $(".col_status").eq(cnt).html("");
        }
    }

    function applyLanguageMode() {
        renderStaticLabels();
        renderTimetableRows(latestTimetableRows);
        renderTicker(latestTickerMessages, latestTickerSpeed, true);
    }

    function setPanelVisibility(element, visible, displayClass) {
        if (!element) {
            return;
        }

        var activeClass = displayClass || "block";
        element.hidden = !visible;
        element.classList.toggle("hidden", !visible);
        element.classList.toggle(activeClass, !!visible);
    }

    function setShellVisibility(visible) {
        if (!displayShellEl) {
            return;
        }

        displayShellEl.classList.toggle("opacity-0", !visible);
        displayShellEl.classList.toggle("pointer-events-none", !visible);
        displayShellEl.setAttribute("aria-hidden", visible ? "false" : "true");
    }

    function clearLanguageSwapTimer() {
        if (languageSwapTimer) {
            window.clearTimeout(languageSwapTimer);
            languageSwapTimer = null;
        }
    }

    function isTimetableVisibleForLanguageSwap() {
        if (!displayShellEl || !tableStageEl) {
            return true;
        }

        return tableStageEl.classList.contains("active") &&
            !displayShellEl.classList.contains("content-active") &&
            !displayShellEl.classList.contains("notify-active") &&
            !displayShellEl.classList.contains("guidance-active");
    }

    function scheduleLanguageSwap(delayMs) {
        if (!englishSwapEnabled) {
            return;
        }

        clearLanguageSwapTimer();
        languageSwapRemainingMs = Math.max(1, parseInt(delayMs, 10) || DISPLAY_LANGUAGE_SWAP_INTERVAL_MS);
        languageSwapStartedAt = Date.now();
        languageSwapTimer = window.setTimeout(function () {
            languageSwapTimer = null;
            languageSwapStartedAt = 0;
            languageSwapRemainingMs = DISPLAY_LANGUAGE_SWAP_INTERVAL_MS;
            currentLanguage = currentLanguage === "en" ? "jp" : "en";
            applyLanguageMode();
            updateLanguageSwapState();
        }, languageSwapRemainingMs);
    }

    function pauseLanguageSwap() {
        if (!englishSwapEnabled || !languageSwapTimer) {
            return;
        }

        languageSwapRemainingMs = Math.max(1, languageSwapRemainingMs - (Date.now() - languageSwapStartedAt));
        languageSwapStartedAt = 0;
        clearLanguageSwapTimer();
    }

    function updateLanguageSwapState() {
        if (!englishSwapEnabled) {
            clearLanguageSwapTimer();
            languageSwapRemainingMs = DISPLAY_LANGUAGE_SWAP_INTERVAL_MS;
            languageSwapStartedAt = 0;
            return;
        }

        if (isTimetableVisibleForLanguageSwap()) {
            if (!languageSwapTimer) {
                scheduleLanguageSwap(languageSwapRemainingMs);
            }
            return;
        }

        pauseLanguageSwap();
    }

    function restartLanguageSwap() {
        clearLanguageSwapTimer();
        languageSwapRemainingMs = DISPLAY_LANGUAGE_SWAP_INTERVAL_MS;
        languageSwapStartedAt = 0;

        currentLanguage = englishSwapEnabled ? "en" : "jp";
        applyLanguageMode();

        updateLanguageSwapState();
    }

    function renderStatusCell(d) {
        if (d.boarding_text) {
            var cls = d.boarding_blink === "1"
                ? "rounded-full border border-amber-300 bg-amber-300 px-4 py-2 text-center text-base font-bold text-slate-950 shadow-lg shadow-amber-300/40 animate-pulse"
                : "rounded-full border border-blue-500 bg-blue-500 px-4 py-2 text-center text-base font-bold text-white shadow-lg shadow-blue-500/30";
            return "<p class='" + cls + "'>" + labelByLanguage("乗船案内中", "Boarding") + "</p>";
        }

        if (mode === 1 && d.boarding_start_hm) {
            var hint = labelByLanguage("乗船 ", "Boarding ") + d.boarding_start_hm +
                labelByLanguage(" / 点灯 ", " / Blink ") + d.blink_start_hm;
            if (d.minutes_to_boarding !== "") {
                hint += isEnglishActive()
                    ? (" (" + d.minutes_to_boarding + " min)")
                    : (" (" + d.minutes_to_boarding + "分)");
            }
            return "<p class='rounded-[22px] border border-white/12 bg-white/6 px-4 py-3 text-sm font-medium leading-6 text-white/74'>" + hint + "</p>";
        }

        switch (String(d.status || "")) {
        case "0":
            return "";
        case "1":
            return "<p class='rounded-full border border-blue-500 bg-blue-500 px-4 py-2 text-center text-base font-bold text-white shadow-lg shadow-blue-500/30'>" + labelByLanguage("乗船案内中", "Boarding") + "</p>";
        case "2":
            return "<p class='rounded-full border border-rose-300 bg-rose-300 px-4 py-2 text-center text-base font-bold text-slate-950 shadow-lg shadow-rose-300/35'>" + labelByLanguage("完売", "Sold Out") + "</p>";
        case "3":
            return "<p class='rounded-full border border-amber-300 bg-amber-300 px-4 py-2 text-center text-base font-bold text-slate-950 shadow-lg shadow-amber-300/40'>" + labelByLanguage("遅延", "Delayed") + "</p>";
        case "4":
            return "<p class='rounded-full border border-fuchsia-300 bg-fuchsia-300 px-4 py-2 text-center text-base font-bold text-slate-950 shadow-lg shadow-fuchsia-300/35'>" + labelByLanguage("乗船遅延中", "Boarding Delayed") + "</p>";
        default:
            return "";
        }
    }

    function stopDisplayRotation() {
        if (displayRotationTimer) {
            window.clearInterval(displayRotationTimer);
            displayRotationTimer = null;
        }
    }

    function normalizeSwapIntervalSeconds(value) {
        var seconds = parseInt(value, 10);
        if (isNaN(seconds)) {
            return DEFAULT_DISPLAY_SWAP_INTERVAL_SECONDS;
        }
        if (seconds <= 0) {
            return 0;
        }
        return seconds;
    }

    function pauseAllContentVideos() {
        if (!contentSlidesEl) {
            return;
        }
        contentSlidesEl.querySelectorAll("video").forEach(function (video) {
            video.pause();
        });
    }

    function activateContentSlide(index) {
        if (!contentSlidesEl) {
            return;
        }

        var slides = contentSlidesEl.querySelectorAll(".content-stage-item");
        var dots = contentIndicatorEl ? contentIndicatorEl.querySelectorAll(".content-stage-dot") : [];
        if (!slides.length) {
            return;
        }

        currentContentIndex = index;
        pauseAllContentVideos();

        slides.forEach(function (slide, slideIndex) {
            var active = slideIndex === index;
            slide.classList.toggle("active", active);
            slide.hidden = !active;
            var video = slide.querySelector("video");
            if (video) {
                if (active) {
                    video.currentTime = 0;
                    var playPromise = video.play();
                    if (playPromise && typeof playPromise.catch === "function") {
                        playPromise.catch(function () {
                            return null;
                        });
                    }
                } else {
                    video.pause();
                }
            }
        });

        Array.prototype.forEach.call(dots, function (dot, dotIndex) {
            dot.classList.toggle("active", dotIndex === index);
            dot.classList.toggle("bg-white", dotIndex === index);
            dot.classList.toggle("w-10", dotIndex === index);
            dot.classList.toggle("bg-white/20", dotIndex !== index);
            dot.classList.toggle("w-3", dotIndex !== index);
        });
    }

    function buildDisplaySequence(items, swapIntervalSeconds) {
        var sequence = [{ type: "timetable" }];
        if (swapIntervalSeconds <= 0) {
            return sequence;
        }

        (items || []).forEach(function (item, index) {
            sequence.push({ type: "content", index: index });
            if (index < items.length - 1) {
                sequence.push({ type: "timetable" });
            }
        });

        return sequence;
    }

    function setActiveDisplay(entry) {
        var showTable = !entry || entry.type === "timetable";

        if (displayShellEl) {
            displayShellEl.classList.toggle("content-active", !showTable);
        }
        if (tableStageEl) {
            tableStageEl.classList.toggle("active", showTable);
            setPanelVisibility(tableStageEl, showTable);
        }
        if (contentStagePanelEl) {
            contentStagePanelEl.classList.toggle("active", !showTable);
            setPanelVisibility(contentStagePanelEl, !showTable);
        }

        updateLanguageSwapState();

        if (showTable) {
            pauseAllContentVideos();
            return;
        }

        activateContentSlide(entry.index);
    }

    function resetDisplayRotation(items, swapIntervalSeconds) {
        var intervalSeconds = normalizeSwapIntervalSeconds(swapIntervalSeconds);
        displaySequence = buildDisplaySequence(items, intervalSeconds);
        currentDisplayIndex = 0;
        stopDisplayRotation();
        setActiveDisplay(displaySequence[currentDisplayIndex]);

        if (displaySequence.length > 1 && intervalSeconds > 0) {
            displayRotationTimer = window.setInterval(function () {
                currentDisplayIndex += 1;
                if (currentDisplayIndex >= displaySequence.length) {
                    currentDisplayIndex = 0;
                }
                setActiveDisplay(displaySequence[currentDisplayIndex]);
            }, intervalSeconds * 1000);
        }
    }

    function contentSignature(items) {
        return JSON.stringify(items.map(function (item) {
            return [
                item.id || 0,
                item.title || "",
                item.content_type || "",
                item.content_value || "",
                item.updated_at || "",
                item.sort_order || 0
            ];
        }));
    }

    function renderNotify(notice) {
        if (!displayShellEl || !notifyPanelEl || !notifyImageEl) {
            return;
        }

        var normalized = notice && notice.active ? {
            active: true,
            key: notice.key || "",
            mode: notice.mode || "",
            label: notice.label || "",
            title: notice.title || "",
            image_path: notice.image_path || "",
            updated_at: notice.updated_at || ""
        } : {
            active: false,
            key: "",
            mode: "",
            label: "",
            title: "",
            image_path: "",
            updated_at: ""
        };

        var signature = JSON.stringify(normalized);
        if (signature !== notifyBackSignature && notifyBackSignature !== "") {
            playSound();
        }
        notifyBackSignature = signature;

        if (normalized.active && guidancePlaying) {
            finishGuidancePlayback();
        }

        displayShellEl.classList.toggle("notify-active", normalized.active);
        setShellVisibility(!normalized.active);
        notifyPanelEl.classList.toggle("active", normalized.active);
        setPanelVisibility(notifyPanelEl, normalized.active);
        if (normalized.active) {
            setPanelVisibility(tableStageEl, false);
            setPanelVisibility(contentStagePanelEl, false);
        } else if (!guidancePlaying) {
            setActiveDisplay(displaySequence[currentDisplayIndex]);
        }
        updateLanguageSwapState();

        if (!normalized.active) {
            notifyImageEl.removeAttribute("src");
            notifyImageEl.alt = "";
            if (notifyLabelEl) {
                notifyLabelEl.textContent = "";
            }
            if (notifyTitleEl) {
                notifyTitleEl.textContent = "";
            }
            return;
        }

        notifyImageEl.src = normalized.image_path;
        notifyImageEl.alt = normalized.title || normalized.label || "notify";
        if (notifyLabelEl) {
            notifyLabelEl.textContent = normalized.label;
        }
        if (notifyTitleEl) {
            notifyTitleEl.textContent = normalized.title || normalized.label;
        }
    }

    function guidanceSignature(config) {
        return JSON.stringify({
            ready: !!(config && config.ready),
            lead_minutes: parseInt(config && config.lead_minutes, 10) || 0,
            items: (config && config.items ? config.items : []).map(function (item) {
                return [
                    item.key || "",
                    item.label || "",
                    item.title || "",
                    item.video_path || "",
                    item.updated_at || ""
                ];
            })
        });
    }

    function normalizeGuidanceConfig(data) {
        var items = (data && Array.isArray(data.items) ? data.items : []).filter(function (item) {
            return item && String(item.video_path || "").trim() !== "";
        }).map(function (item) {
            return {
                key: item.key || "",
                label: item.label || "",
                title: item.title || "",
                video_path: item.video_path || "",
                updated_at: item.updated_at || ""
            };
        });

        return {
            ready: !!(data && data.ready) && items.length >= 2,
            lead_minutes: parseInt(data && data.lead_minutes, 10) || 0,
            items: items,
            updated_at: data && data.updated_at ? data.updated_at : ""
        };
    }

    function localDateString(date) {
        return [
            date.getFullYear(),
            String(date.getMonth() + 1).padStart(2, "0"),
            String(date.getDate()).padStart(2, "0")
        ].join("-");
    }

    function guidanceTriggerForRows(rows, leadMinutes) {
        if (!rows || !rows.length || leadMinutes <= 0) {
            return null;
        }

        var now = new Date();
        var today = localDateString(now);
        for (var i = 0; i < rows.length; i += 1) {
            var row = rows[i] || {};
            var time = String(row.time || "").substr(0, 5);
            if (!/^\d{2}:\d{2}$/.test(time)) {
                continue;
            }

            var departure = new Date(today + "T" + time + ":00");
            if (isNaN(departure.getTime())) {
                continue;
            }
            if (departure.getTime() <= now.getTime()) {
                continue;
            }

            var trigger = new Date(departure.getTime() - (leadMinutes * 60 * 1000));
            return {
                key: stationId + ":" + today + ":" + time,
                triggerAt: trigger,
                departureAt: departure,
                time: time
            };
        }

        return null;
    }

    function cleanupGuidanceVideo() {
        if (!guidanceVideoEl) {
            return;
        }
        guidanceVideoEl.pause();
        guidanceVideoEl.removeAttribute("src");
        guidanceVideoEl.load();
        guidanceVideoEl.onended = null;
        guidanceVideoEl.onerror = null;
    }

    function finishGuidancePlayback() {
        guidancePlaying = false;
        guidanceQueue = [];
        guidanceCurrentIndex = 0;
        if (guidanceSoundTimeout) {
            window.clearTimeout(guidanceSoundTimeout);
            guidanceSoundTimeout = null;
        }

        if (displayShellEl) {
            displayShellEl.classList.remove("guidance-active");
        }
        if (!notifyPanelEl || !notifyPanelEl.classList.contains("active")) {
            setShellVisibility(true);
        }
        if (guidancePanelEl) {
            guidancePanelEl.classList.remove("active");
            setPanelVisibility(guidancePanelEl, false);
        }
        if (guidanceLabelEl) {
            guidanceLabelEl.textContent = "";
        }
        if (guidanceTitleEl) {
            guidanceTitleEl.textContent = "";
        }
        cleanupGuidanceVideo();
        resetDisplayRotation(currentContentItems, currentContentSwapInterval);
    }

    function playGuidanceItem(index) {
        if (!guidanceVideoEl || !guidanceLabelEl || !guidanceTitleEl) {
            finishGuidancePlayback();
            return;
        }

        if (index >= guidanceQueue.length) {
            finishGuidancePlayback();
            return;
        }

        guidanceCurrentIndex = index;
        var item = guidanceQueue[index];
        guidanceLabelEl.textContent = item.label || "";
        guidanceTitleEl.textContent = item.title || item.label || "";
        guidanceVideoEl.src = item.video_path || "";
        guidanceVideoEl.currentTime = 0;
        guidanceVideoEl.onended = function () {
            playGuidanceItem(index + 1);
        };
        guidanceVideoEl.onerror = function () {
            playGuidanceItem(index + 1);
        };

        var playPromise = guidanceVideoEl.play();
        if (playPromise && typeof playPromise.catch === "function") {
            playPromise.catch(function () {
                playGuidanceItem(index + 1);
            });
        }
    }

    function startGuidancePlayback(triggerKey) {
        if (!guidancePanelEl || !guidanceConfig.ready || guidancePlaying) {
            return;
        }
        if (displayShellEl && displayShellEl.classList.contains("notify-active")) {
            return;
        }

        guidancePlaying = true;
        guidanceQueue = guidanceConfig.items.slice(0, 2);
        lastGuidanceTriggerKey = triggerKey;
        stopDisplayRotation();
        pauseAllContentVideos();

        if (displayShellEl) {
            displayShellEl.classList.add("guidance-active");
        }
        setShellVisibility(false);
        guidancePanelEl.classList.add("active");
        setPanelVisibility(guidancePanelEl, true);
        setPanelVisibility(tableStageEl, false);
        setPanelVisibility(contentStagePanelEl, false);
        updateLanguageSwapState();

        playSound();
        guidanceSoundTimeout = window.setTimeout(function () {
            guidanceSoundTimeout = null;
            playGuidanceItem(0);
        }, 1000);
    }

    function evaluateGuidancePlayback() {
        if (guidancePlaying || !guidanceConfig.ready) {
            return;
        }
        if (displayShellEl && displayShellEl.classList.contains("notify-active")) {
            return;
        }

        var trigger = guidanceTriggerForRows(latestTimetableRows, guidanceConfig.lead_minutes);
        if (!trigger) {
            return;
        }

        var now = new Date();
        if (now.getTime() >= trigger.triggerAt.getTime() &&
            now.getTime() < trigger.departureAt.getTime() &&
            lastGuidanceTriggerKey !== trigger.key) {
            startGuidancePlayback(trigger.key);
        }
    }

    function renderGuidanceConfig(data) {
        var normalized = normalizeGuidanceConfig(data || {});
        var signature = guidanceSignature(normalized);
        if (signature === guidanceBackSignature) {
            evaluateGuidancePlayback();
            return;
        }

        guidanceBackSignature = signature;
        guidanceConfig = normalized;
        evaluateGuidancePlayback();
    }

    function renderContent(items, swapIntervalSeconds) {
        if (!contentSlidesEl || !contentEmptyEl || !contentIndicatorEl || !contentTitleEl) {
            return;
        }

        var intervalSeconds = normalizeSwapIntervalSeconds(swapIntervalSeconds);
        var signature = contentSignature(items || []);
        if (signature === contentBackSignature && intervalSeconds === contentBackIntervalSeconds) {
            currentContentItems = items || [];
            currentContentSwapInterval = intervalSeconds;
            return;
        }

        stopDisplayRotation();
        contentSlidesEl.innerHTML = "";
        contentIndicatorEl.innerHTML = "";
        contentBackSignature = signature;
        contentBackIntervalSeconds = intervalSeconds;
        currentContentItems = items || [];
        currentContentSwapInterval = intervalSeconds;
        currentContentIndex = 0;

        if (!items || !items.length) {
            resetDisplayRotation([], intervalSeconds);
            contentEmptyEl.style.display = "flex";
            contentTitleEl.textContent = "放映コンテンツ";
            return;
        }

        contentEmptyEl.style.display = "none";
        contentTitleEl.textContent = items.length > 1 ? "放映コンテンツ" : (items[0].title || "放映コンテンツ");

        items.forEach(function (item, index) {
            var slide = document.createElement("div");
            slide.className = "content-stage-item absolute inset-0 h-full w-full overflow-hidden";
            slide.hidden = index !== 0;

            if (String(item.content_type) === "movie") {
                var video = document.createElement("video");
                video.src = item.content_value || "";
                video.muted = true;
                video.playsInline = true;
                video.preload = "auto";
                video.loop = items.length === 1;
                video.className = "h-full w-full object-cover";
                slide.appendChild(video);
            } else {
                var image = document.createElement("img");
                image.src = item.content_value || "";
                image.alt = item.title || "content";
                image.className = "h-full w-full object-cover";
                slide.appendChild(image);
            }

            var title = document.createElement("div");
            title.className = "content-stage-title absolute inset-x-6 bottom-6 rounded-[22px] border border-white/12 bg-slate-950/60 px-5 py-4 text-2xl font-bold text-white backdrop-blur";
            title.textContent = item.title || "コンテンツ";
            slide.appendChild(title);
            contentSlidesEl.appendChild(slide);

            var dot = document.createElement("span");
            dot.className = "content-stage-dot h-3 rounded-full bg-white/20 transition-all duration-200";
            if (index === 0) {
                dot.classList.add("active");
                dot.classList.add("bg-white", "w-10");
            } else {
                dot.classList.add("w-3");
            }
            contentIndicatorEl.appendChild(dot);
        });

        resetDisplayRotation(items, intervalSeconds);
    }

    function checkUpdate() {
        var now = new Date();
        if (now.getSeconds() % 10 === 0) {
            renderDisplay();
            return;
        }

        $.ajax({
            type: "POST",
            url: "cgi/get_status.php",
            dataType: "json",
            data: { id: stationId },
            success: function (data) {
                var st = data.status;
                if (st !== status) {
                    renderDisplay();
                }
                status = st;
            }
        });
    }

    function renderDisplay() {
        $.ajax({
            type: "POST",
            url: "cgi/getdisplay.php",
            dataType: "json",
            data: { id: stationId, mode: mode },
            success: function (data) {
                var changed = false;
                var rows = Array.isArray(data) ? data.slice() : [];
                latestTimetableRows = rows;

                for (var i = 0; i < rows.length; i++) {
                    var d = rows[i];
                    if (d.time !== timeBack[i] ||
                        d.ship !== shipBack[i] ||
                        d.station !== stationBack[i] ||
                        d.soldout !== soldoutBack[i] ||
                        d.status !== statusBack[i] ||
                        d.detail !== detailBack[i] ||
                        d.badge_label !== badgeBack[i]) {
                        changed = true;
                    }
                    timeBack[i] = d.time;
                    shipBack[i] = d.ship;
                    stationBack[i] = d.station;
                    soldoutBack[i] = d.soldout;
                    statusBack[i] = d.status;
                    detailBack[i] = d.detail;
                    badgeBack[i] = d.badge_label || "";
                }

                if (changed) {
                    playSound();
                }

                renderTimetableRows(rows);
                evaluateGuidancePlayback();
            }
        });

        $.ajax({
            type: "POST",
            url: "cgi/getmessage.php",
            dataType: "json",
            data: { id: stationId, mode: mode },
            success: function (data) {
                latestTickerMessages = Array.isArray(data.messages) ? data.messages : [];
                latestTickerSpeed = data && typeof data.drag_speed !== "undefined" ? data.drag_speed : 4;
                renderTicker(latestTickerMessages, latestTickerSpeed, false);
            }
        });

        $.ajax({
            type: "POST",
            url: "cgi/getcontent.php",
            dataType: "json",
            data: { id: stationId },
            success: function (data) {
                renderContent(
                    (data && data.items) ? data.items : [],
                    data && typeof data.swap_interval_seconds !== "undefined" ? data.swap_interval_seconds : DEFAULT_DISPLAY_SWAP_INTERVAL_SECONDS
                );
            }
        });

        $.ajax({
            type: "POST",
            url: "cgi/getnotify.php",
            dataType: "json",
            data: { id: stationId },
            success: function (data) {
                renderNotify(data || {});
            }
        });

        $.ajax({
            type: "POST",
            url: "cgi/getguidance.php",
            dataType: "json",
            success: function (data) {
                renderGuidanceConfig(data || {});
            }
        });
    }

    $("#btnRtn").on("click", function () {
        window.location.href = "timetable.php?s=" + stationId;
    });

    restartLanguageSwap();
    updateClock();
    setInterval(updateClock, 1000);
    renderDisplay();

    if (mode === 0) {
        setInterval(checkUpdate, 1000);
    }
});
