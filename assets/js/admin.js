/*!
 * b83 Schneebericht – Backend.
 * Autosave, Schnellaktionen und Struktur-Verwaltung.
 */
(function ($) {
	"use strict";

	var cfg = window.b83Schneebericht || { i18n: {} };
	var t = cfg.i18n;

	/* ------------------------------------------------------------------
	 * Autosave (Statusformular)
	 * ---------------------------------------------------------------- */
	var $form = $("#b83-sb-form");
	var $status = $("#b83-sb-autosave-status");
	var dirty = false;
	var saving = false;
	var timer = null;

	function setStatus(text, cls) {
		if (!$status.length) return;
		$status.removeClass("is-idle is-dirty is-saving is-saved is-error").addClass(cls).text(text);
	}

	function syncEditors() {
		if (window.tinymce && typeof window.tinymce.triggerSave === "function") {
			window.tinymce.triggerSave();
		}
	}

	function markDirty() {
		dirty = true;
		setStatus(t.dirty, "is-dirty");
		clearTimeout(timer);
		timer = setTimeout(autosave, 2500);
	}

	function autosave() {
		if (!dirty || saving || !$form.length) return;
		saving = true;
		dirty = false;
		syncEditors();
		setStatus(t.saving, "is-saving");

		var data = $form.serializeArray().filter(function (f) {
			return f.name !== "action" && f.name !== "b83_sb_nonce" && f.name !== "_wp_http_referer";
		});
		data.push({ name: "action", value: "b83_sb_autosave" });
		data.push({ name: "nonce", value: cfg.autosaveNonce });

		$.post(cfg.ajaxUrl, $.param(data), null, "json")
			.done(function (resp) {
				if (resp && resp.success) {
					setStatus(t.saved.replace("%s", resp.data.time || ""), "is-saved");
				} else {
					dirty = true;
					setStatus(t.error, "is-error");
				}
			})
			.fail(function () {
				dirty = true;
				setStatus(t.error, "is-error");
			})
			.always(function () {
				saving = false;
			});
	}

	if ($form.length) {
		setStatus(t.ready, "is-idle");

		$form.on("input change", "input, textarea, select", markDirty);

		// Änderungen im Richtext-Editor (TinyMCE) erkennen.
		$(document).on("tinymce-editor-init", function (event, editor) {
			editor.on("input change keyup undo redo", markDirty);
		});

		// Vor Tab-Wechsel noch speichern.
		$(".nav-tab-wrapper a").on("click", function () {
			if (dirty) autosave();
		});

		$form.on("submit", function () {
			dirty = false;
		});

		window.addEventListener("beforeunload", function (e) {
			if (dirty || saving) {
				e.preventDefault();
				e.returnValue = t.leave;
				return t.leave;
			}
		});

		// Ampel-Farbe im Select direkt anzeigen.
		$form.on("change", "select.b83-sb-ampel", function () {
			this.className = this.className.replace(/b83-sb-ampel--\w+/, "") + " b83-sb-ampel--" + this.value;
		});

		// „Heute“-Knopf beim Datum.
		$form.on("click", ".b83-sb-today", function () {
			var d = new Date();
			var v = d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
			$("#" + $(this).data("target")).val(v).trigger("change");
		});

		// Alle öffnen / schließen.
		$form.on("click", ".b83-sb-all", function () {
			var open = String($(this).data("value")) === "1";
			$form.find("input.b83-sb-open").prop("checked", open);
			markDirty();
		});
	}

	/* ------------------------------------------------------------------
	 * Struktur-Verwaltung
	 * ---------------------------------------------------------------- */
	var $structure = $("#b83-sb-structure");
	if ($structure.length) {
		var counter = 0;

		if ($.fn.sortable) {
			$structure.find(".b83-sb-groups").sortable({ handle: ".b83-sb-group__head .b83-sb-handle", axis: "y" });
			$structure.find(".b83-sb-items__body").sortable({ handle: ".b83-sb-handle", axis: "y" });
		}

		$structure.on("click", ".b83-sb-add-item", function () {
			var $group = $(this).closest(".b83-sb-group");
			var tpl = $group.find(".b83-sb-item-template").html();
			counter++;
			var $row = $(tpl.replace(/__KEY__/g, "n_" + counter));
			$group.find(".b83-sb-items__body").append($row);
			$row.find("input[type=text]").trigger("focus");
		});

		$structure.on("submit", function (e) {
			var deleting = $structure.find("input[name$='[delete]']:checked").length > 0;
			if (deleting && !window.confirm(t.confirmDelete)) {
				e.preventDefault();
			}
		});

		// Gelöschte Zeilen sichtbar markieren.
		$structure.on("change", "input[name$='[delete]']", function () {
			var $scope = $(this).closest($(this).hasClass("b83-sb-delete-item") ? "tr" : ".b83-sb-group");
			$scope.toggleClass("is-deleted", this.checked);
		});
	}
})(jQuery);
