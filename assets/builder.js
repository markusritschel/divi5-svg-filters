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
    var visible = function (e) {
      return filterOf(e && e.attr) === filterId;
    };
    var fields = [];
    var field = function (
      fieldKey,
      label,
      description,
      dflt,
      component,
      features,
    ) {
      var tune = {};
      tune[filterId] = {};
      tune[filterId][fieldKey] = dflt;
      fields.push(
        Object.assign({}, common, {
          subName: "svgTune." + filterId + "." + fieldKey,
          label: label,
          description: description || undefined,
          defaultAttr: { desktop: { value: { svgTune: tune } } },
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
            c.default,
            { type: "field", name: "divi/color-picker", props: {} },
            { dynamicContent: { type: "color" } },
          );
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
          field(
            c.key + "@x",
            c.label + " (" + I.horiz + ")",
            c.description,
            withUnit(c.default_x, c),
            range(c.min, c.max, c.step, c.unit),
          );
          field(
            c.key + "@y",
            c.label + " (" + I.vert + ")",
            "",
            withUnit(c.default_y, c),
            range(c.min, c.max, c.step, c.unit),
          );
          break;
        case "levels":
          field(
            c.key + "@count",
            c.label,
            c.description,
            String(c.count),
            range(2, 10, 1, ""),
          );
          if (c === levels[levels.length - 1]) {
            field(
              "@min",
              I.darkest,
              I.levelsDesc,
              number(c.lo),
              range(0, 1, 0.01, ""),
            );
            field(
              "@max",
              I.brightest,
              I.levelsDesc,
              number(c.hi),
              range(0, 1, 0.01, ""),
            );
          }
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

  try {
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
