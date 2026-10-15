/*!
 * b83 Schneebericht – Einbettung auf fremden Seiten.
 *
 * <div class="b83-sb-embed" data-origin="https://example.at/" data-section="loipen_postalm"
 *      data-items="auloipe_1,auloipe_2" data-title="0"></div>
 * <script async src="https://example.at/wp-content/plugins/b83-schneebericht/assets/js/embed.js"></script>
 *
 * Alte Container mit der Klasse "tvb-sb-embed" werden ebenfalls erkannt.
 */
(function () {
	"use strict";

	var frames = [];

	function buildUrl(origin, params) {
		var base = origin.replace(/\/+$/, "") + "/";
		var q = [];
		for (var k in params) {
			if (!Object.prototype.hasOwnProperty.call(params, k)) continue;
			if (params[k] === null || params[k] === undefined || params[k] === "") continue;
			q.push(encodeURIComponent(k) + "=" + encodeURIComponent(String(params[k])));
		}
		return base + "?" + q.join("&");
	}

	function initOne(container) {
		if (container.getAttribute("data-b83-ready")) return;
		container.setAttribute("data-b83-ready", "1");

		var origin = container.getAttribute("data-origin") || "";
		var section = container.getAttribute("data-section") || "";

		if (!/^https?:\/\//i.test(origin) || !section) {
			container.textContent = "Schneebericht: data-origin oder data-section fehlt.";
			return;
		}

		var src = buildUrl(origin, {
			b83_schneebericht_embed: section,
			items: container.getAttribute("data-items") || "",
			title: container.getAttribute("data-title") === "0" ? "0" : "",
			template: container.getAttribute("data-template") || ""
		});

		var iframe = document.createElement("iframe");
		iframe.src = src;
		iframe.loading = "lazy";
		iframe.title = container.getAttribute("data-label") || "Schneebericht";
		iframe.setAttribute("scrolling", "no");
		iframe.style.cssText = "width:100%;border:0;display:block;overflow:hidden;height:200px;";

		frames.push({ iframe: iframe });

		container.innerHTML = "";
		container.appendChild(iframe);
	}

	// Ein gemeinsamer Listener: jede Höhenmeldung wird nur dem iFrame zugeordnet, das sie gesendet hat.
	window.addEventListener("message", function (e) {
		var d = e.data;
		if (!d || d.type !== "b83-sb-embed-height") return;
		for (var i = 0; i < frames.length; i++) {
			var f = frames[i];
			// Nur das eigene iFrame darf seine Höhe setzen (Quelle statt Herkunft prüfen,
			// damit Weiterleitungen z. B. auf www. nicht stören).
			if (f.iframe.contentWindow !== e.source) continue;
			var h = parseInt(d.height, 10);
			if (isFinite(h) && h >= 20 && h < 20000) f.iframe.style.height = h + "px";
			return;
		}
	});

	function initAll() {
		var nodes = document.querySelectorAll(".b83-sb-embed, .tvb-sb-embed");
		for (var i = 0; i < nodes.length; i++) initOne(nodes[i]);
	}

	if (document.readyState === "loading") {
		document.addEventListener("DOMContentLoaded", initAll);
	} else {
		initAll();
	}
})();
