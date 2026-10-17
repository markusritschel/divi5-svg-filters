/**
 * SVG Filters for Divi 5 — Visual Builder (app window).
 *
 * 1. Fields below "Blend" in every Filters group: SVG Filter (grouped select),
 *    Apply to (element / background only), a "Tune ‹filter›" heading with
 *    presets and reset, and the filter's tuning values as real Divi fields
 *    (range with unit, color picker with global colors, text, a 3×3 matrix).
 *    They write svgTune[<filter id>][<field key>] (see includes/Tweaks.php).
 * 2. Live preview: each module gets a <style> next to it with the same rules
 *    as the frontend (Divi's own StyleStatements, url(#id) in front of Divi's
 *    filter functions); tuned copies go into a plugin-owned <svg> defs block.
 *
 * Every callback returns its input unchanged on error: the builder keeps working.
 */
(function () {
  "use strict";

  var D = window.D5SVGF;
  var core = window.D5SVGFCore;
  var vendor = window.vendor || {};
  var hooks = vendor.wp && vendor.wp.hooks;
  var React = vendor.React;
  var divi = window.divi || {};
  if (!D || !core || !hooks || !React) return;

  var h = React.createElement;
  var I = D.i18n;

  /** Display unit → a CSS property for which Divi's range field shows that unit (verified, Divi 5.15.1). */
  var UNIT_PROPERTY = {
    px: "filter:blur",
    deg: "transform:rotate",
    s: "transition-duration",
    "%": "filter:opacity",
  };

  function warn(where, err) {
    if (window.console) console.warn("[SVG Filters for Divi 5] " + where, err);
  }

  /** Chosen, available filter of a Filters attribute, or ''. */
  function filterOf(attr) {
    var v = attr && attr.desktop && attr.desktop.value;
    var id = v && typeof v.svgFilter === "string" ? v.svgFilter : "";
    return id && id !== "none" && D.filters[id] ? id : "";
  }

  function number(n) {
    return String(Math.round(n * 1e6) / 1e6);
  }

  /* ---------- 1. Fields ---------- */

  var selectOptions = { none: { label: I.none } };
  D.groups.forEach(function (group, i) {
    var options = {};
    Object.keys(group.options).forEach(function (id) {
      options[id] = { label: group.options[id] };
    });
    selectOptions["d5svgf-group-" + i] = {
      label: group.label,
      options: options,
    };
  });

  /** Divi fields for one filter's controls; visible only while that filter is chosen. */
  function tuneFields(common, filterId) {
    var filter = D.filters[filterId];
    var chosen = function (e) {
      return filterOf(e && e.attr) === filterId;
    };
    /** Level fields per channel ('@channels' 'each') or shared. */
    var eachChannel = function (e) {
      var tune = e.attr.desktop.value.svgTune;
      return !!(tune && tune[filterId] && tune[filterId]["@channels"] === "each");
    };
    var fields = [];
    var field = function (
      fieldKey,
      label,
      description,
      dflt,
      component,
      features,
      when,
    ) {
      var visible = when
        ? function (e) {
            return chosen(e) && when(e);
          }
        : chosen;
      // fieldKey null: the field gets the filter's whole svgTune entry (e.g. the 2D pad).
      var tune = {};
      tune[filterId] = {};
      if (fieldKey !== null) tune[filterId][fieldKey] = dflt;
      fields.push(
        Object.assign({}, common, {
          subName:
            "svgTune." + filterId + (fieldKey !== null ? "." + fieldKey : ""),
          label: label,
          description: description || undefined,
          defaultAttr:
            fieldKey !== null
              ? { desktop: { value: { svgTune: tune } } }
              : undefined,
          visible: visible,
          features: Object.assign({}, common.features, features || {}),
          component: component,
        }),
      );
    };
    var range = function (min, max, step, unit) {
      var props = {
        min: min,
        max: max,
        minLimit: min,
        maxLimit: max,
        cssSliderUI: true,
      };
      if (typeof step === "number") props.step = step;
      if (UNIT_PROPERTY[unit]) props.cssProperty = UNIT_PROPERTY[unit];
      else props.defaultUnit = ""; // without it Divi falls back to px
      return { type: "field", name: "divi/range", props: props };
    };
    var withUnit = function (n, c) {
      return number(n) + (UNIT_PROPERTY[c.unit] ? c.unit : "");
    };

    var levels = filter.controls.filter(function (c) {
      return c.type === "levels";
    });
    filter.controls.forEach(function (c) {
      switch (c.type) {
        case "color":
          field(
            c.key,
            c.label,
            c.description,
            c.default_css || c.default,
            { type: "field", name: "divi/color-picker", props: {} },
            { dynamicContent: { type: "color" } },
          );
          break;
        case "alpha": // the linked color picker's opacity
          break;
        case "range":
          field(
            c.key,
            c.label,
            c.description,
            withUnit(c.default, c),
            range(c.min, c.max, c.step, c.unit),
          );
          break;
        case "pair":
          field(null, c.label, c.description, null, {
            type: "field",
            name: "d5svgf/pad",
            props: {
              tweak: c.key,
              min: c.min,
              max: c.max,
              step: c.step,
              dx: c.default_x,
              dy: c.default_y,
              unit: UNIT_PROPERTY[c.unit] ? c.unit : "",
              noise: c.key.indexOf("feTurbulence") === 0,
            },
          });
          field(
            c.key + "@x",
            I.horiz,
            "",
            withUnit(c.default_x, c),
            range(c.min, c.max, c.step, c.unit),
          );
          field(
            c.key + "@y",
            I.vert,
            "",
            withUnit(c.default_y, c),
            range(c.min, c.max, c.step, c.unit),
          );
          break;
        case "levels":
          var together = function (e) {
            return !eachChannel(e);
          };
          if (c === levels[0]) {
            field(
              "@channels",
              I.channels,
              I.channelsDesc,
              "all",
              {
                type: "field",
                name: "divi/button-options",
                props: {
                  options: {
                    all: { label: I.together },
                    each: { label: I.each },
                  },
                  showLabel: true,
                  fullwidth: true,
                },
              },
            );
            field(
              "@count",
              I.levels,
              I.levelsDesc,
              String(c.count),
              range(2, 10, 1, ""),
              null,
              together,
            );
            field(
              "@min",
              I.darkest,
              I.spreadDesc,
              number(c.lo),
              range(0, 1, 0.01, ""),
              null,
              together,
            );
            field(
              "@max",
              I.brightest,
              I.spreadDesc,
              number(c.hi),
              range(0, 1, 0.01, ""),
              null,
              together,
            );
          }
          var channel = /^feFunc([RGBA])\b/.exec(c.key);
          var name = channel ? I.channel[channel[1]] : c.label;
          field(
            c.key + "@count",
            c.label,
            c.description,
            String(c.count),
            range(2, 10, 1, ""),
            null,
            eachChannel,
          );
          field(
            c.key + "@min",
            I.darkestOf.replace("%s", name),
            I.spreadDesc,
            number(c.lo),
            range(0, 1, 0.01, ""),
            null,
            eachChannel,
          );
          field(
            c.key + "@max",
            I.brightestOf.replace("%s", name),
            I.spreadDesc,
            number(c.hi),
            range(0, 1, 0.01, ""),
            null,
            eachChannel,
          );
          break;
        case "matrix":
          field(c.key, c.label, c.description, c.default, {
            type: "field",
            name: "d5svgf/matrix",
            props: { fallback: c.default },
          });
          break;
        default:
          field(c.key, c.label, c.description, c.default, {
            type: "field",
            name: "divi/text",
            props: {},
          });
      }
    });
    return fields;
  }

  hooks.addFilter(
    "divi.module.options.filters.group.fields",
    "d5svgf/fields",
    function (fields, ctx) {
      try {
        var blend = fields.blendMode || {};
        var common = {
          attrName: ctx.attrName,
          groupName: blend.groupName || "divi/filters",
          category: blend.category || "layout",
          render: true,
          features: { hover: false, sticky: false, responsive: false },
        };
        var out = Object.assign({}, fields);
        out.svgFilter = Object.assign({}, common, {
          subName: "svgFilter",
          label: I.svgFilter,
          description: I.desc,
          component: {
            type: "field",
            name: "divi/select",
            props: { options: selectOptions },
          },
        });
        out.svgScope = Object.assign({}, common, {
          subName: "svgScope",
          label: I.applyTo,
          defaultAttr: { desktop: { value: { svgScope: "element" } } },
          visible: function (e) {
            return !!filterOf(e && e.attr);
          },
          component: {
            type: "field",
            name: "divi/button-options",
            props: {
              options: {
                element: { label: I.element },
                background: { label: I.bg },
              },
              showLabel: true,
              fullwidth: true,
            },
          },
        });
        out.svgTuneHead = Object.assign({}, common, {
          subName: "svgTune",
          label: "",
          visible: function (e) {
            var id = filterOf(e && e.attr);
            return !!id && D.filters[id].controls.length > 0;
          },
          component: { type: "field", name: "d5svgf/tune-head", props: {} },
        });
        Object.keys(D.filters).forEach(function (filterId) {
          tuneFields(common, filterId).forEach(function (f, i) {
            out["d5svgfTune_" + filterId + "_" + i] = f;
          });
        });
        return out;
      } catch (err) {
        warn("fields", err);
        return fields;
      }
    },
  );

  /** "Tune ‹filter›" heading: presets and "Reset all" (writes the whole svgTune). */
  function TuneHead(props) {
    var id = filterOf(props.attr);
    var filter = id && D.filters[id];
    if (!filter) return null;
    var all = props.value && typeof props.value === "object" ? props.value : {};
    var mine =
      all[id] && typeof all[id] === "object" && Object.keys(all[id]).length
        ? all[id]
        : null;
    var write = function (next) {
      var out = Object.assign({}, all);
      if (next) out[id] = next;
      else delete out[id];
      props.onChange({ inputValue: out });
    };
    var presets = filter.presets || {};
    var names = Object.keys(presets);
    var colors = filter.controls.filter(function (c) {
      return c.type === "color";
    });
    var swap = function () {
      var next = Object.assign({}, mine || {});
      var current = colors.map(function (c) {
        return Object.prototype.hasOwnProperty.call(next, c.key)
          ? next[c.key]
          : c.default_css || c.default;
      });
      next[colors[0].key] = current[1];
      next[colors[1].key] = current[0];
      write(next);
    };
    return h(
      "div",
      { className: "d5svgf-tune-head" },
      h(
        "div",
        { className: "d5svgf-tune-head__row" },
        h(
          "span",
          { className: "d5svgf-tune-head__title" },
          I.tune.replace("%s", filter.label),
        ),
        h(
          "span",
          { className: "d5svgf-tune-head__actions" },
          colors.length === 2
            ? h(
                "button",
                {
                  type: "button",
                  className: "d5svgf-tune-head__reset d5svgf-tune-head__swap",
                  onClick: swap,
                },
                I.swap,
              )
            : null,
          mine
            ? h(
                "button",
                {
                  type: "button",
                  className: "d5svgf-tune-head__reset",
                  onClick: function () {
                    write(null);
                  },
                },
                I.reset,
              )
            : null,
        ),
      ),
      names.length
        ? h(
            "div",
            {
              className: "d5svgf-presets",
              role: "group",
              "aria-label": I.presets,
            },
            names.map(function (name) {
              return h(
                "button",
                {
                  key: name,
                  type: "button",
                  className:
                    "et-vb-button et-vb-button--secondary d5svgf-presets__button",
                  onClick: function () {
                    write(core.presetFields(filter, presets[name]));
                  },
                },
                name,
              );
            }),
          )
        : null,
    );
  }

  /** 3×3 kernel as nine number fields; writes the row-by-row list. */
  function Matrix(props) {
    var raw =
      typeof props.value === "string" && props.value.trim()
        ? props.value
        : props.fallback || "0 0 0 0 1 0 0 0 0";
    var cells = raw
      .trim()
      .split(/[\s,]+/)
      .slice(0, 9);
    while (cells.length < 9) cells.push("0");
    var commit = function (i, value) {
      var next = cells.slice();
      next[i] = String(value).trim() || "0";
      if (next.join(" ") !== cells.join(" "))
        props.onChange({ inputValue: next.join(" ") });
    };
    return h(
      "div",
      { className: "d5svgf-matrix", key: raw },
      cells.map(function (v, i) {
        return h("input", {
          key: i,
          type: "text",
          inputMode: "decimal",
          spellCheck: false,
          className: "et-vb-field-input et-vb-field-input-text",
          defaultValue: v,
          "aria-label":
            "Row " + (Math.floor(i / 3) + 1) + ", column " + ((i % 3) + 1),
          onBlur: function (e) {
            commit(i, e.target.value);
          },
          onKeyDown: function (e) {
            if (e.key === "Enter") commit(i, e.target.value);
          },
        });
      }),
    );
  }

  /**
   * 2D pad for a {pair}: drag the dot (or use the arrow keys, Shift for bigger steps) to set the
   * horizontal and vertical value together; written on release, so a drag is one undo step.
   * Turbulence frequencies get a noise swatch at the same pixel scale as on the page.
   */
  function Pad(props) {
    var tune = props.value && typeof props.value === "object" ? props.value : {};
    var k = props.tweak;
    var min = props.min;
    var max = props.max;
    var step = typeof props.step === "number" ? props.step : (max - min) / 100;
    var drag = React.useState(null);
    var noiseId = React.useState(function () {
      return "d5svgf-noise-" + Math.random().toString(36).slice(2, 10);
    })[0];
    var clampTo = function (n) {
      return Math.min(max, Math.max(min, n));
    };
    var read = function (v, fallback) {
      var n = parseFloat(v);
      return isFinite(n) ? clampTo(n) : fallback;
    };
    var snap = function (n) {
      return clampTo(Math.round((n - min) / step) * step + min);
    };
    var saved = [read(tune[k + "@x"], props.dx), read(tune[k + "@y"], props.dy)];
    var shown = drag[0] || saved;
    var commit = function (xy) {
      var next = Object.assign({}, tune);
      next[k + "@x"] = number(xy[0]) + props.unit;
      next[k + "@y"] = number(xy[1]) + props.unit;
      props.onChange({ inputValue: next });
    };
    var at = function (e) {
      var r = e.currentTarget.getBoundingClientRect();
      var fx = Math.min(1, Math.max(0, (e.clientX - r.left) / r.width));
      var fy = Math.min(1, Math.max(0, (r.bottom - e.clientY) / r.height));
      return [snap(min + (max - min) * fx), snap(min + (max - min) * fy)];
    };
    var pct = function (n) {
      return ((n - min) / (max - min)) * 100 + "%";
    };
    var keys = {
      ArrowLeft: [-1, 0],
      ArrowRight: [1, 0],
      ArrowUp: [0, 1],
      ArrowDown: [0, -1],
    };
    return h(
      "div",
      { className: "d5svgf-pad-row" },
      h(
        "div",
        {
          className: "d5svgf-pad",
          tabIndex: 0,
          role: "group",
          "aria-label":
            I.horiz + " " + number(shown[0]) + ", " + I.vert + " " + number(shown[1]),
          onPointerDown: function (e) {
            e.currentTarget.setPointerCapture(e.pointerId);
            drag[1](at(e));
          },
          onPointerMove: function (e) {
            if (drag[0]) drag[1](at(e));
          },
          onPointerUp: function (e) {
            if (!drag[0]) return;
            commit(at(e));
            drag[1](null);
          },
          onKeyDown: function (e) {
            var d = keys[e.key];
            if (!d) return;
            e.preventDefault();
            var f = e.shiftKey ? 10 : 1;
            commit([
              snap(saved[0] + d[0] * step * f),
              snap(saved[1] + d[1] * step * f),
            ]);
          },
        },
        h("span", {
          className: "d5svgf-pad__line d5svgf-pad__line--x",
          style: { bottom: pct(shown[1]) },
        }),
        h("span", {
          className: "d5svgf-pad__line d5svgf-pad__line--y",
          style: { left: pct(shown[0]) },
        }),
        h("span", {
          className: "d5svgf-pad__dot",
          style: { left: pct(shown[0]), bottom: pct(shown[1]) },
        }),
        h("span", { className: "d5svgf-pad__axis d5svgf-pad__axis--x" }, I.horiz + " →"),
        h("span", { className: "d5svgf-pad__axis d5svgf-pad__axis--y" }, I.vert + " →"),
      ),
      props.noise
        ? h(
            "svg",
            {
              className: "d5svgf-pad__noise",
              width: 72,
              height: 72,
              "aria-hidden": true,
            },
            h(
              "filter",
              { id: noiseId, x: 0, y: 0, width: "100%", height: "100%" },
              h("feTurbulence", {
                type: "fractalNoise",
                baseFrequency: number(shown[0]) + " " + number(shown[1]),
                numOctaves: 2,
              }),
              h("feColorMatrix", { type: "saturate", values: "0" }),
            ),
            h("rect", { width: 72, height: 72, filter: "url(#" + noiseId + ")" }),
          )
        : null,
    );
  }

  try {
    divi.fieldLibrary.registerFieldComponent({
      name: "d5svgf/pad",
      component: Pad,
    });
    divi.fieldLibrary.registerFieldComponent({
      name: "d5svgf/tune-head",
      component: TuneHead,
    });
    divi.fieldLibrary.registerFieldComponent({
      name: "d5svgf/matrix",
      component: Matrix,
    });
  } catch (err) {
    warn("field components", err);
  }

  /* ---------- 2. Live preview ---------- */

  var defs = null;

  function defsHost() {
    if (!defs || !defs.isConnected) {
      defs = document.createElementNS("http://www.w3.org/2000/svg", "svg");
      defs.setAttribute("id", "d5svgf-vb-defs");
      defs.setAttribute("aria-hidden", "true");
      defs.setAttribute(
        "style",
        "position:absolute;width:0;height:0;overflow:hidden",
      );
      document.body.appendChild(defs);
    }
    return defs;
  }

  /** Tuned / per-element copy in the app document (as the PHP Registry::copy). */
  function copy(id, tune, salt) {
    var filter = D.filters[id];
    var values = core.composeAll(filter, tune);
    if (!Object.keys(values).length && !salt) return null;
    var newId = core.copyId(id, values, salt);
    if (document.getElementById(newId)) return { id: newId };

    var doc = new DOMParser().parseFromString(
      '<svg xmlns="http://www.w3.org/2000/svg">' + filter.markup + "</svg>",
      "image/svg+xml",
    );
    var el = doc.querySelector("filter");
    if (!el || doc.querySelector("parsererror")) return null;
    var suffix = newId.slice(id.length);
    var seen = {};
    var prims = {};
    Array.prototype.forEach.call(el.getElementsByTagName("*"), function (p) {
      var tag = p.localName;
      seen[tag] = (seen[tag] || 0) + 1;
      prims[
        p.hasAttribute("id")
          ? "#" + p.getAttribute("id")
          : seen[tag] === 1
            ? tag
            : tag + ":" + seen[tag]
      ] = p;
    });
    Object.keys(values).forEach(function (key) {
      var parts = key.split("|");
      var p = prims[parts[0]];
      if (!p) return;
      if (values[key].value !== null)
        p.setAttribute(parts[1], values[key].value);
      if (values[key].style !== null) {
        var style = (p.getAttribute("style") || "").replace(/;\s*$/, "");
        p.setAttribute("style", (style ? style + ";" : "") + values[key].style);
      }
    });
    Array.prototype.forEach.call(el.getElementsByTagName("*"), function (p) {
      if (p.hasAttribute("id"))
        p.setAttribute("id", p.getAttribute("id") + suffix);
    });
    el.setAttribute("id", newId);
    defsHost().appendChild(document.importNode(el, true));
    return { id: newId };
  }

  /** Declaration: url(#id) (if any) in front of Divi's resolved filter functions. */
  function declaration(filterId) {
    return function (p) {
      var resolved =
        divi.moduleUtils.getAttrValue({
          attr: p.attr || {},
          breakpoint: p.breakpoint || "desktop",
          state: p.state || "value",
          mode: "getAndInheritAll",
          defaultValue: p.attrValue || {},
        }) || {};
      var parts = filterId ? ["url(#" + filterId + ")"] : [];
      Object.keys(D.functions).forEach(function (k) {
        if (resolved[k]) parts.push(D.functions[k] + "(" + resolved[k] + ")");
      });
      return parts.length ? "filter:" + parts.join(" ") + ";" : "";
    };
  }

  /**
   * The module's own attrs. In the builder, the wrapper hook's ctx.attrs already has the preset
   * merged in (unlike the server's); the store keeps them apart, as the frontend does.
   */
  function ownAttrs(ctx) {
    var store = vendor.wp.data && vendor.wp.data.select("divi/edit-post");
    var mod = store && store.getModule ? store.getModule(ctx.id) : null;
    return (mod && mod.props && mod.props.attrs) || ctx.attrs || {};
  }

  /** The module preset as a layer (group presets: frontend only for now). */
  function presetLayer(ctx, attrs) {
    var utils = divi.moduleUtils;
    if (!utils.getActiveModulePreset || !utils.getPresetClassNameForModule)
      return null;
    var preset = utils.getActiveModulePreset({
      moduleName: ctx.name,
      moduleAttrs: attrs,
    });
    if (!preset || !preset.attrs) return null;
    var cls = utils.getPresetClassNameForModule(ctx.name, preset, null);
    // Deep merge like Divi's array_replace_recursive on the server (lodash is global in the builder).
    var merged =
      preset.styleAttrs && window.lodash
        ? window.lodash.merge({}, preset.attrs, preset.styleAttrs)
        : preset.attrs;
    return cls ? { cls: cls, attrs: merged, shared: true } : null;
  }

  /** Selector templates per module, from the builder's module metadata (module.json). */
  var templateCache = {};
  function templatesFor(name) {
    if (!templateCache[name]) {
      var store =
        vendor.wp.data && vendor.wp.data.select("divi/module-library");
      var meta = store && store.getModule ? store.getModule(name) : null;
      templateCache[name] = core.templates(meta && meta.attributes);
    }
    return templateCache[name];
  }

  hooks.addFilter(
    "divi.module.wrapper.render",
    "d5svgf/preview",
    function (el, ctx) {
      try {
        var templates = templatesFor(ctx.name);
        var order =
          Object.keys(templates).length &&
          divi.moduleUtils.getModuleOrderClassName
            ? divi.moduleUtils.getModuleOrderClassName(ctx.id)
            : "";
        if (!order) return el;

        var attrs = ownAttrs(ctx);
        var layers = [];
        var preset = presetLayer(ctx, attrs);
        if (preset) layers.push(preset);
        // Every module repeats the shared preset rules in its own <style>; a later module's copy
        // must not beat this module's rules, so they get one more class (the frontend prints
        // shared rules once, before all module rules).
        layers.push({ cls: order, attrs: attrs, shared: false, repeat: 3 });

        var plan = core.plan({
          layers: layers,
          templates: templates,
          has: function (id) {
            return !!D.filters[id];
          },
          copy: copy,
          interactive: D.interactive,
          functions: Object.keys(D.functions),
        });
        if (plan.missing.length)
          warn("filter not found, not applied: " + plan.missing.join(", "));
        if (!plan.chunks.length) return el;

        var StyleStatements = divi.module.StyleStatements;
        var rules = plan.chunks.map(function (c, i) {
          if (c.kind === "background")
            return core.backgroundCss(c.selector, c.filterId, c.base, D.edge);
          if (c.kind === "none") return c.selector + "{filter:none}";
          return h(StyleStatements, {
            key: i,
            selectors: { desktop: { value: c.selector } },
            attr: c.filters,
            orderClass: "." + order,
            declarationFunction: declaration(c.filterId),
          });
        });

        var wrapper = el;
        if (plan.interactive && el && el.props) {
          var extra = {};
          extra["data-svgf-" + plan.interactive[0]] = plan.interactive[1];
          wrapper = React.cloneElement(el, {
            htmlAttrs: Object.assign({}, el.props.htmlAttrs, extra),
          });
        }
        return h(
          React.Fragment,
          null,
          wrapper,
          h("style", { "data-d5svgf": ctx.id }, rules),
        );
      } catch (err) {
        warn("preview", err);
        return el;
      }
    },
  );
})();
