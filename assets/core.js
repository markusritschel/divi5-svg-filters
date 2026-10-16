/**
 * SVG Filters for Divi 5 — pure builder logic, no DOM.
 *
 * Mirrors includes/Tweaks.php (value checks) and the layer logic of
 * includes/Frontend.php, so the builder preview follows the frontend rules.
 * Browser: window.D5SVGFCore. Node tests: module.exports.
 */
(function (root, factory) {
  var core = factory();
  if (typeof module === "object" && module.exports) module.exports = core;
  else root.D5SVGFCore = core;
})(typeof self !== "undefined" ? self : this, function () {
  "use strict";

  var MAX_TEXT = 200;
  var NUMBER = /^[+-]?(\d+\.?\d*|\.\d+)(e[+-]?\d+)?$/i;

  /** Locale-independent number without float noise or trailing zeros (as Tweaks::number). */
  function number(n) {
    var out = n.toFixed(6).replace(/0+$/, "").replace(/\.$/, "");
    return out === "-0" ? "0" : out;
  }

  /** Validated attribute value for a control, or null (as Tweaks::sanitize). */
  function sanitize(control, value) {
    if (typeof value !== "string" && typeof value !== "number") return null;
    if (control.type === "color") {
      return typeof value === "string" && /^#[0-9a-f]{6}$/i.test(value)
        ? value.toLowerCase()
        : null;
    }
    if (control.type === "range") {
      if (typeof value === "string") {
        var m = /^\s*(-?\d*\.?\d+(?:e-?\d+)?)\s*([a-z%]*)\s*$/i.exec(value);
        if (!m || (m[2] !== "" && m[2] !== control.unit)) return null;
        value = parseFloat(m[1]);
      }
      if (!isFinite(value)) return null;
      return (
        number(Math.min(control.max, Math.max(control.min, value))) +
        control.unit
      );
    }
    var tokens = String(value)
      .trim()
      .split(/[\s,]+/)
      .filter(Boolean);
    var list = tokens.join(" ");
    if (!tokens.length || list.length > MAX_TEXT) return null;
    for (var i = 0; i < tokens.length; i++)
      if (!NUMBER.test(tokens[i])) return null;
    return list;
  }

  function isDefault(control, value) {
    if (control.type === "range")
      return Math.abs(parseFloat(value) - control.default) < 1e-9;
    if (control.type === "text")
      return sanitize(control, control.default) === value;
    return control.default === value;
  }

  /** Valid, non-default tuned values of a filter, sorted by key. */
  function tuneValues(filter, tune) {
    var out = {};
    if (!filter || !tune || typeof tune !== "object") return out;
    filter.controls
      .slice()
      .sort(function (a, b) {
        return a.key < b.key ? -1 : a.key > b.key ? 1 : 0;
      })
      .forEach(function (c) {
        if (!Object.prototype.hasOwnProperty.call(tune, c.key)) return;
        var v = sanitize(c, tune[c.key]);
        if (v !== null && !isDefault(c, v)) out[c.key] = v;
      });
    return out;
  }

  /** FNV-1a, 8 hex digits. */
  function hash(str) {
    var h = 0x811c9dc5;
    for (var i = 0; i < str.length; i++) {
      h ^= str.charCodeAt(i);
      h = Math.imul(h, 0x01000193) >>> 0;
    }
    return ("0000000" + h.toString(16)).slice(-8);
  }

  /** Builder-only copy ID ('v' keeps it apart from the frontend's md5-based IDs). */
  function copyId(id, values, salt) {
    return id + "--v" + hash(JSON.stringify([id, values, salt || ""]));
  }

  function valid(name) {
    return /^[A-Za-z][\w-]*$/.test(name);
  }

  /**
   * Which rules a module needs (as Frontend::process/element).
   *
   * @param {object} args
   *   layers: [{cls, attrs, shared}] in cascade order (presets first, the module last)
   *   templates: element => selector template ('{{selector}} …')
   *   has(id): filter offered?
   *   copy(id, tune, salt): {id} of a tuned/instance copy, or null for the shared filter
   *   interactive: filter ID => kind ('spotlight', 'melt')
   * @return {{chunks: Array, missing: string[], interactive: Array|null}}
   *   chunk.kind: 'element' (url + Divi values), 'divi' (Divi values restated), 'background'
   */
  function plan(args) {
    var chunks = [],
      missing = [],
      interactive = null,
      covered = {};
    args.layers.forEach(function (layer) {
      if (!valid(layer.cls)) return;
      Object.keys(args.templates).forEach(function (element) {
        var filters =
          layer.attrs &&
          layer.attrs[element] &&
          layer.attrs[element].decoration &&
          layer.attrs[element].decoration.filters;
        if (!filters || typeof filters !== "object") return;
        var selector = args.templates[element].replace(
          "{{selector}}",
          "." + layer.cls + "." + layer.cls,
        );
        var value = (filters.desktop && filters.desktop.value) || {};
        var svg =
          typeof value.svgFilter === "string" && value.svgFilter !== "none"
            ? value.svgFilter
            : "";
        if (svg && !args.has(svg)) {
          if (missing.indexOf(svg) < 0) missing.push(svg);
          svg = "";
        }
        var restate = covered[element]
          ? { kind: "divi", selector: selector, filters: filters, filterId: "" }
          : null;
        if (!svg) {
          if (restate) chunks.push(restate);
          covered[element] = covered[element] || !!restate;
          return;
        }

        var kind = args.interactive[svg] || "";
        var tune =
          value.svgTune && typeof value.svgTune === "object"
            ? value.svgTune[svg]
            : null;
        var made = args.copy(
          svg,
          tune && typeof tune === "object" ? tune : null,
          kind ? layer.cls : "",
        );
        var filterId = made ? made.id : svg;
        if (!valid(filterId)) return;
        if (kind && !layer.shared && element === "module")
          interactive = [kind, filterId];

        if (value.svgScope === "background") {
          chunks.push({
            kind: "background",
            selector: selector,
            filterId: filterId,
            base: svg,
            tune: tune,
          });
          if (restate) chunks.push(restate);
          covered[element] = covered[element] || !!restate;
          return;
        }
        chunks.push({
          kind: "element",
          selector: selector,
          filters: filters,
          filterId: filterId,
          base: svg,
          tune: tune,
        });
        covered[element] = true;
      });
    });
    return { chunks: chunks, missing: missing, interactive: interactive };
  }

  /** The kit's --bg mechanics for one selector (as Frontend::background_rules). */
  function backgroundCss(s, filterId, baseId, edge) {
    var inset = edge.indexOf(baseId) >= 0 ? "-5%" : "0";
    return (
      ":where(" +
      s +
      "){position:relative;isolation:isolate;overflow:hidden}" +
      s +
      "::before{content:'';position:absolute;inset:" +
      inset +
      ";z-index:-1;background:inherit;filter:url(#" +
      filterId +
      ");pointer-events:none}" +
      s +
      " .et_parallax_bg," +
      s +
      " .et-pb-parallax-background{filter:url(#" +
      filterId +
      ")}"
    );
  }

  /**
   * element => selector template from a module's metadata attributes (module.json), as
   * Frontend::selector_templates: plain single selectors starting with '{{selector}}'.
   */
  function templates(attributes) {
    var out = {};
    Object.keys(attributes || {}).forEach(function (element) {
      var s = attributes[element] && attributes[element].selector;
      if (typeof s === "string" && s.indexOf("{{selector}}") === 0 && s.indexOf(",") < 0) out[element] = s;
    });
    return out;
  }

  return {
    sanitize: sanitize,
    isDefault: isDefault,
    tuneValues: tuneValues,
    hash: hash,
    copyId: copyId,
    plan: plan,
    backgroundCss: backgroundCss,
    templates: templates,
  };
});
