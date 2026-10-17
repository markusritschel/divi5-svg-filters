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

  var LEVELS_MIN = 2;
  var LEVELS_MAX = 10;

  function has(obj, key) {
    return Object.prototype.hasOwnProperty.call(obj, key);
  }

  /** Numbers of a list, or [] if any token is not numeric. */
  function numbers(list) {
    var tokens = String(list).trim().split(/[\s,]+/).filter(Boolean);
    for (var i = 0; i < tokens.length; i++) if (!NUMBER.test(tokens[i])) return [];
    return tokens.map(parseFloat);
  }

  /** Number list as Tweaks::sanitize does for text, or null. */
  function list(value) {
    if (typeof value !== "string" && typeof value !== "number") return null;
    var tokens = String(value).trim().split(/[\s,]+/).filter(Boolean);
    var out = tokens.join(" ");
    if (!tokens.length || out.length > MAX_TEXT) return null;
    for (var i = 0; i < tokens.length; i++) if (!NUMBER.test(tokens[i])) return null;
    return out;
  }

  /** Number from a field value: plain, with the display unit, or with the SVG suffix. */
  function parseNumber(value, control) {
    if (typeof value === "number") return isFinite(value) ? value : null;
    if (typeof value !== "string") return null;
    var m = /^\s*(-?\d*\.?\d+(?:e-?\d+)?)\s*([a-z%]*)\s*$/i.exec(value);
    if (!m) return null;
    var unit = m[2].toLowerCase();
    if (unit && unit !== (control.unit || "") && unit !== (control.suffix || "")) return null;
    return parseFloat(m[1]);
  }

  function clamp(n, min, max) {
    return Math.min(max, Math.max(min, n));
  }

  /** Hex or rgb(a) color, normalized as Tweaks::sanitize does (Divi stores rgba() below 100% opacity). */
  function plainColor(raw) {
    if (/^#[0-9a-f]{6}$/i.test(raw)) return raw.toLowerCase();
    var m = /^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*(\d*\.?\d+)\s*)?\)$/i.exec(raw);
    if (!m || Math.max(+m[1], +m[2], +m[3]) > 255) return null;
    var alpha = m[4] !== undefined && m[4] !== "" ? parseFloat(m[4]) : 1;
    return alpha > 1 ? null : "rgba(" + +m[1] + ", " + +m[2] + ", " + +m[3] + ", " + number(alpha) + ")";
  }

  /** CSS for a Divi global color reference (as Tweaks::color_variable + Divi's transform), or null. */
  function colorVariable(raw) {
    var m = /^\$variable\(([\s\S]*)\)\$$/.exec(raw);
    if (!m) return null;
    var data;
    try {
      data = JSON.parse(m[1]);
    } catch (e) {
      return null;
    }
    var name = data && data.value && data.value.name;
    if (!data || data.type !== "color" || typeof name !== "string" || !/^gcid-[a-z0-9-]+$/i.test(name)) return null;
    var settings = {};
    var raw2 = (data.value && data.value.settings) || {};
    ["hue", "saturation", "lightness", "opacity"].forEach(function (k) {
      if (has(raw2, k) && raw2[k] !== null && raw2[k] !== "" && isFinite(raw2[k])) settings[k] = Number(raw2[k]);
    });
    var css = transform("var(--" + name + ")", settings);
    return /^[a-z0-9\s(),.%+\-\/]+$/i.test(css) ? css : null;
  }

  /** Port of Divi 5.15.1 GlobalData::transform_state_into_global_color_value(). */
  function transform(cssVariable, settings) {
    if (!Object.keys(settings).length) return cssVariable;
    var hue = settings.hue || 0;
    var sat = settings.saturation || 0;
    var light = settings.lightness || 0;
    var hasOpacity = has(settings, "opacity");
    var opacity = hasOpacity ? settings.opacity : null;
    var printed = opacity !== null && (opacity !== 100 || hasOpacity) ? " / " + opacity / 100 : "";
    var fmt = function (base, adj) {
      return "calc(" + base + (adj >= 0 ? " + " : " - ") + Math.abs(adj) + ")";
    };
    if (!(hue | 0) && !(sat | 0) && !(light | 0) && (opacity === null || ((opacity | 0) === 100 && !hasOpacity))) return cssVariable;
    var s = fmt("s", sat);
    if (sat < 0) s = "max(0, " + s + ")";
    return "hsl(from " + cssVariable + " " + fmt("h", hue) + " " + s + " " + fmt("l", light) + printed + ")";
  }

  /**
   * Attribute value or style declaration for a control from the filter's field values
   * (as Tweaks::compose). null: nothing valid or only the default.
   */
  function compose(c, tune) {
    var key = c.key;
    var out = function (value) {
      return { value: value, style: null };
    };
    tune = tune && typeof tune === "object" ? tune : {};

    if (c.type === "color") {
      var raw = tune[key];
      if (typeof raw !== "string") return null;
      // Builder: '$variable(…)$'; the frontend gets Divi's already resolved CSS (accepted the same way).
      var css = colorVariable(raw);
      if (css === null && /^(?:var\(--gcid-[a-z0-9-]+\)|hsl\(from var\(--gcid-[a-z0-9-]+\)[a-z0-9\s(),.%+\-\/]*\))$/i.test(raw)) css = raw;
      if (css !== null) return { value: null, style: c.attr + ":" + css };
      var color = plainColor(raw);
      return color !== null && color !== (c.default_css || c.default) ? out(color) : null;
    }
    if (c.type === "alpha") {
      var linked = compose(c.color, tune);
      var own = compose(Object.assign({}, c, { type: "range" }), tune);
      // Untouched color, or a plain hex next to a value saved with the former opacity slider.
      if (linked === null || (own !== null && linked.value !== null && linked.value.charAt(0) === "#")) return own;
      return Math.abs(c.default - 1) < 1e-9 ? null : out("1");
    }
    if (c.type === "range") {
      if (!has(tune, key)) return null;
      var n = parseNumber(tune[key], c);
      if (n === null) return null;
      n = clamp(n, c.min, c.max);
      return Math.abs(n - c.default) < 1e-9 ? null : out(number(n) + c.suffix);
    }
    if (c.type === "pair") {
      var legacy = has(tune, key) ? numbers(list(tune[key]) || "") : [];
      var part = function (value, fallback) {
        var p = value === undefined || value === null ? null : parseNumber(value, c);
        return clamp(p === null ? fallback : p, c.min, c.max);
      };
      var x = part(tune[key + "@x"], legacy.length ? legacy[0] : c.default_x);
      var y = part(tune[key + "@y"], legacy.length > 1 ? legacy[1] : c.default_y);
      if (Math.abs(x - c.default_x) < 1e-9 && Math.abs(y - c.default_y) < 1e-9) return null;
      return out(number(x) + " " + number(y));
    }
    if (c.type === "levels") {
      // '@channels' 'each': per-channel fields '<key>@count|@min|@max'; otherwise the shared '@count|@min|@max'.
      var prefix = tune["@channels"] === "each" ? key : "";
      if (!has(tune, prefix + "@count") && !has(tune, prefix + "@min") && !has(tune, prefix + "@max")) {
        var saved = has(tune, key) ? list(tune[key]) : null; // saved by 0.1/0.2 as a plain list
        return saved !== null && saved !== list(c.default) ? out(saved) : null;
      }
      var num = function (value, min, max, fallback) {
        return clamp(value !== undefined && value !== "" && isFinite(value) ? Number(value) : fallback, min, max);
      };
      var count = Math.round(num(tune[prefix + "@count"], LEVELS_MIN, LEVELS_MAX, c.count));
      var lo = num(tune[prefix + "@min"], 0, 1, c.lo);
      var hi = num(tune[prefix + "@max"], 0, 1, c.hi);
      var levels = [];
      for (var i = 0; i < count; i++) levels.push(number(lo + ((hi - lo) * i) / (count - 1)));
      return out(levels.join(" "));
    }
    var value = has(tune, key) ? list(tune[key]) : null;
    if (value === null) return null;
    if (c.type === "matrix" && numbers(value).length !== 9) return null;
    return value !== list(c.default) ? out(value) : null;
  }

  /** Changed controls of a filter: key → {value, style}, sorted by key (hash input for copies). */
  function composeAll(filter, tune) {
    var result = {};
    if (!filter) return result;
    filter.controls
      .slice()
      .sort(function (a, b) {
        return a.key < b.key ? -1 : a.key > b.key ? 1 : 0;
      })
      .forEach(function (c) {
        var composed = compose(c, tune);
        if (composed) result[c.key] = composed;
      });
    return result;
  }

  /** Field values (svgTune[<filter id>]) for a preset given as attribute values. */
  function presetFields(filter, preset) {
    var fields = {};
    filter.controls.forEach(function (c) {
      if (!has(preset, c.key)) return;
      var raw = String(preset[c.key]);
      if (c.type === "color") {
        if (!/^#[0-9a-f]{6}$/i.test(raw)) return;
        var alpha = c.alpha_key ? Number(has(preset, c.alpha_key) ? preset[c.alpha_key] : c.alpha) : 1;
        fields[c.key] =
          alpha >= 1 || !isFinite(alpha)
            ? raw.toLowerCase()
            : "rgba(" + [1, 3, 5].map(function (i) { return parseInt(raw.substr(i, 2), 16); }).join(", ") + ", " + number(clamp(alpha, 0, 1)) + ")";
      } else if (c.type === "alpha") {
        // set through the linked color
      } else if (c.type === "range") {
        var n = parseNumber(raw, c);
        if (n !== null) fields[c.key] = number(clamp(n, c.min, c.max)) + (c.unit || c.suffix || "");
      } else if (c.type === "pair") {
        var xy = numbers(raw);
        if (xy.length === 2) {
          fields[c.key + "@x"] = number(xy[0]);
          fields[c.key + "@y"] = number(xy[1]);
        }
      } else if (c.type === "levels") {
        var lv = numbers(raw);
        if (lv.length >= LEVELS_MIN) {
          fields["@channels"] = "each";
          fields[c.key + "@count"] = String(Math.min(LEVELS_MAX, lv.length));
          fields[c.key + "@min"] = number(Math.min.apply(null, lv));
          fields[c.key + "@max"] = number(Math.max.apply(null, lv));
        }
      } else {
        var l = list(raw);
        if (l !== null) fields[c.key] = l;
      }
    });
    return fields;
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
   *   layers: [{cls, attrs, shared, repeat}] in cascade order (presets first, the module last);
   *     repeat: how often the class is repeated in the selector (default 2)
   *   templates: element => selector template ('{{selector}} …')
   *   has(id): filter offered?
   *   copy(id, tune, salt): {id} of a tuned/instance copy, or null for the shared filter
   *   interactive: filter ID => kind ('spotlight', 'melt')
   *   functions: Divi filter function keys (hueRotate, saturate, …)
   * @return {{chunks: Array, missing: string[], interactive: Array|null}}
   *   chunk.kind: 'element' (url + Divi values), 'divi' (Divi values restated),
   *   'none' (filter:none), 'background'
   */
  function plan(args) {
    var chunks = [],
      missing = [],
      interactive = null,
      states = {};

    /** Any Divi filter value on any breakpoint/state (as Frontend: rules not empty). */
    function hasDivi(filters) {
      return Object.keys(filters).some(function (bp) {
        var states = filters[bp];
        return states && typeof states === "object" && Object.keys(states).some(function (s) {
          var v = states[s];
          return v && typeof v === "object" && args.functions.some(function (k) { return !!v[k]; });
        });
      });
    }

    /** Raised rule without an SVG filter: own Divi values, else the latest earlier ones, else filter:none. */
    function withoutSvg(state, filters, own, selector) {
      if (own) return { kind: "divi", selector: selector, filters: filters, filterId: "" };
      if (state.last) return { kind: "divi", selector: selector, filters: state.last, filterId: "" };
      return { kind: "none", selector: selector };
    }

    args.layers.forEach(function (layer) {
      if (!valid(layer.cls)) return;
      Object.keys(args.templates).forEach(function (element) {
        var state = (states[element] = states[element] || { covered: false, carry: null, last: null });
        var filters =
          layer.attrs &&
          layer.attrs[element] &&
          layer.attrs[element].decoration &&
          layer.attrs[element].decoration.filters;
        if (!filters || typeof filters !== "object") return;
        // Class repeated (default twice) to raise specificity above Divi's own rules.
        var selector = args.templates[element].replace(
          "{{selector}}",
          new Array((layer.repeat || 2) + 1).join("." + layer.cls),
        );
        var value = (filters.desktop && filters.desktop.value) || {};
        var svg = typeof value.svgFilter === "string" ? value.svgFilter : "";
        if (svg && svg !== "none" && !args.has(svg)) {
          if (missing.indexOf(svg) < 0) missing.push(svg);
          svg = ""; // like unset: an inherited filter stays
        }
        var own = hasDivi(filters);

        if (!svg) {
          // Unset: keep the inherited SVG filter; this layer's own Divi values replace earlier ones.
          if (state.covered && own) {
            chunks.push(state.carry
              ? { kind: "element", selector: selector, filters: filters, filterId: state.carry.id, base: state.carry.base }
              : { kind: "divi", selector: selector, filters: filters, filterId: "" });
          }
          if (own) state.last = filters;
          return;
        }
        if (svg === "none") {
          state.carry = null;
          if (state.covered) chunks.push(withoutSvg(state, filters, own, selector));
          if (own) state.last = filters;
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
          // The element's own filter stays Divi's; an inherited raised rule must not keep its SVG filter.
          if (state.covered) chunks.push(withoutSvg(state, filters, own, selector));
          state.carry = null;
          if (own) state.last = filters;
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
        state.covered = true;
        state.carry = { id: filterId, base: svg };
        if (own) state.last = filters;
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
    compose: compose,
    composeAll: composeAll,
    colorVariable: colorVariable,
    presetFields: presetFields,
    hash: hash,
    copyId: copyId,
    plan: plan,
    backgroundCss: backgroundCss,
    templates: templates,
  };
});
