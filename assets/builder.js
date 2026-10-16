/**
 * SVG Filters for Divi 5 — Visual Builder (app window).
 *
 * 1. Fields below "Blend" in every Filters group: SVG Filter (grouped select),
 *    Apply to (element / background only) and a "Tune" fold-out.
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

  function warn(where, err) {
    if (window.console) console.warn("[SVG Filters for Divi 5] " + where, err);
  }

  /** Chosen, available filter of a Filters attribute, or ''. */
  function filterOf(attr) {
    var v = attr && attr.desktop && attr.desktop.value;
    var id = v && typeof v.svgFilter === "string" ? v.svgFilter : "";
    return id && id !== "none" && D.filters[id] ? id : "";
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
        out.svgTune = Object.assign({}, common, {
          subName: "svgTune",
          label: "",
          visible: function (e) {
            var id = filterOf(e && e.attr);
            return !!id && D.filters[id].controls.length > 0;
          },
          component: { type: "field", name: "d5svgf/tune", props: {} },
        });
        return out;
      } catch (err) {
        warn("fields", err);
        return fields;
      }
    },
  );

  /* ---------- Tune fold-out ---------- */

  function sprintf(format, a, b) {
    return format.replace("%1$s", a).replace("%2$d", b);
  }

  function Control(props) {
    var c = props.control;
    var current =
      props.value !== undefined
        ? String(props.value)
        : String(c.type === "range" ? c.default + c.unit : c.default);
    var label = h(
      "label",
      { className: "d5svgf-tune__label", title: c.attr },
      c.label,
    );

    if (c.type === "color") {
      return h(
        "div",
        { className: "d5svgf-tune__row" },
        label,
        h("input", {
          type: "color",
          value: current,
          onChange: function (e) {
            props.onSet(e.target.value);
          },
        }),
      );
    }
    if (c.type === "range") {
      var num = parseFloat(current);
      return h(
        "div",
        { className: "d5svgf-tune__row" },
        label,
        h(
          "div",
          { className: "d5svgf-tune__range" },
          h("input", {
            type: "range",
            min: c.min,
            max: c.max,
            step: c.step,
            value: num,
            onChange: function (e) {
              props.onSet(e.target.value);
            },
          }),
          h("input", {
            type: "number",
            className: "et-vb-field-input et-vb-field-input-text",
            min: c.min,
            max: c.max,
            step: c.step,
            value: num,
            onChange: function (e) {
              props.onSet(e.target.value);
            },
          }),
          c.unit ? h("span", { className: "d5svgf-tune__unit" }, c.unit) : null,
        ),
      );
    }
    // Number lists: write on blur/Enter, so typing is not interrupted by validation.
    return h(
      "div",
      { className: "d5svgf-tune__row" },
      label,
      h("input", {
        type: "text",
        className: "et-vb-field-input et-vb-field-input-text", // Divi's own input look
        defaultValue: current,
        spellCheck: false,
        onBlur: function (e) {
          props.onSet(e.target.value);
        },
        onKeyDown: function (e) {
          if (e.key === "Enter") props.onSet(e.target.value);
        },
      }),
    );
  }

  function Tune(props) {
    var state = React.useState(false);
    var open = state[0];
    var setOpen = state[1];
    var id = filterOf(props.attr);
    var filter = id && D.filters[id];
    if (!filter || !filter.controls.length) return null;

    var all = props.value && typeof props.value === "object" ? props.value : {};
    var mine = all[id] && typeof all[id] === "object" ? all[id] : {};

    function write(next) {
      var out = Object.assign({}, all);
      if (next && Object.keys(next).length) out[id] = next;
      else delete out[id];
      props.onChange({ inputValue: out });
    }
    function set(control, raw) {
      var next = Object.assign({}, mine);
      var v = core.sanitize(control, raw);
      if (v === null) return; // invalid input: keep the stored value
      if (core.isDefault(control, v)) delete next[control.key];
      else next[control.key] = v;
      write(next);
    }

    return h(
      "div",
      { className: "d5svgf-tune" + (open ? " d5svgf-tune--open" : "") },
      h(
        "div",
        { className: "d5svgf-tune__head" },
        h(
          "button",
          {
            type: "button",
            className: "d5svgf-tune__toggle",
            "aria-expanded": open,
            onClick: function () {
              setOpen(!open);
            },
          },
          (open ? "▾ " : "▸ ") +
            sprintf(I.tune, filter.label, filter.controls.length),
        ),
        Object.keys(mine).length
          ? h(
              "button",
              {
                type: "button",
                className: "d5svgf-tune__reset",
                onClick: function () {
                  write(null);
                },
              },
              I.reset,
            )
          : null,
      ),
      open
        ? h(
            "div",
            { className: "d5svgf-tune__body" },
            filter.controls.map(function (c) {
              return h(Control, {
                key: id + "|" + c.key,
                control: c,
                value: mine[c.key],
                onSet: function (v) {
                  set(c, v);
                },
              });
            }),
          )
        : null,
    );
  }

  try {
    divi.fieldLibrary.registerFieldComponent({
      name: "d5svgf/tune",
      component: Tune,
    });
  } catch (err) {
    warn("tune component", err);
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

  /** Tuned / per-element copy in the app document (same primitive keys as the PHP Registry::copy). */
  function copy(id, tune, salt) {
    var filter = D.filters[id];
    var values = core.tuneValues(filter, tune);
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
      if (prims[parts[0]]) prims[parts[0]].setAttribute(parts[1], values[key]);
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
    var attrs =
      preset.styleAttrs && window.lodash
        ? window.lodash.merge({}, preset.attrs, preset.styleAttrs)
        : preset.attrs;
    return cls ? { cls: cls, attrs: attrs, shared: true } : null;
  }

  /** Selector templates per module, from the builder's module metadata (module.json). */
  var templateCache = {};
  function templatesFor(name) {
    if (!templateCache[name]) {
      var store = vendor.wp.data && vendor.wp.data.select("divi/module-library");
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
          Object.keys(templates).length && divi.moduleUtils.getModuleOrderClassName
            ? divi.moduleUtils.getModuleOrderClassName(ctx.id)
            : "";
        if (!order) return el;

        var attrs = ownAttrs(ctx);
        var layers = [];
        var preset = presetLayer(ctx, attrs);
        if (preset) layers.push(preset);
        layers.push({ cls: order, attrs: attrs, shared: false });

        var plan = core.plan({
          layers: layers,
          templates: templates,
          has: function (id) {
            return !!D.filters[id];
          },
          copy: copy,
          interactive: D.interactive,
        });
        if (plan.missing.length)
          warn("filter not found, not applied: " + plan.missing.join(", "));
        if (!plan.chunks.length) return el;

        var StyleStatements = divi.module.StyleStatements;
        var rules = plan.chunks.map(function (c, i) {
          if (c.kind === "background")
            return core.backgroundCss(c.selector, c.filterId, c.base, D.edge);
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
