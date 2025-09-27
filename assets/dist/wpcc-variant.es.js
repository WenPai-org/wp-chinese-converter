var G = typeof globalThis < "u" ? globalThis : typeof window < "u" ? window : typeof global < "u" ? global : typeof self < "u" ? self : {};
function ue(v) {
  return v && v.__esModule && Object.prototype.hasOwnProperty.call(v, "default") ? v.default : v;
}
var ae = { exports: {} }, J = { exports: {} };
/*! https://mths.be/punycode v1.4.0 by @mathias */
J.exports;
var ne;
function ce() {
  return ne || (ne = 1, function(v, d) {
    (function(_) {
      var y = d && !d.nodeType && d, p = v && !v.nodeType && v, m = typeof G == "object" && G;
      (m.global === m || m.window === m || m.self === m) && (_ = m);
      var n, l = 2147483647, s = 36, P = 1, z = 26, S = 38, g = 700, M = 72, j = 128, R = "-", F = /^xn--/, N = /[^\x20-\x7E]/, $ = /[\x2E\u3002\uFF0E\uFF61]/g, E = {
        overflow: "Overflow: input needs wider integers to process",
        "not-basic": "Illegal input >= 0x80 (not a basic code point)",
        "invalid-input": "Invalid input"
      }, L = s - P, q = Math.floor, T = String.fromCharCode, H;
      function D(c) {
        throw new RangeError(E[c]);
      }
      function K(c, f) {
        for (var h = c.length, w = []; h--; )
          w[h] = f(c[h]);
        return w;
      }
      function W(c, f) {
        var h = c.split("@"), w = "";
        h.length > 1 && (w = h[0] + "@", c = h[1]), c = c.replace($, ".");
        var b = c.split("."), k = K(b, f).join(".");
        return w + k;
      }
      function X(c) {
        for (var f = [], h = 0, w = c.length, b, k; h < w; )
          b = c.charCodeAt(h++), b >= 55296 && b <= 56319 && h < w ? (k = c.charCodeAt(h++), (k & 64512) == 56320 ? f.push(((b & 1023) << 10) + (k & 1023) + 65536) : (f.push(b), h--)) : f.push(b);
        return f;
      }
      function Y(c) {
        return K(c, function(f) {
          var h = "";
          return f > 65535 && (f -= 65536, h += T(f >>> 10 & 1023 | 55296), f = 56320 | f & 1023), h += T(f), h;
        }).join("");
      }
      function e(c) {
        return c - 48 < 10 ? c - 22 : c - 65 < 26 ? c - 65 : c - 97 < 26 ? c - 97 : s;
      }
      function r(c, f) {
        return c + 22 + 75 * (c < 26) - ((f != 0) << 5);
      }
      function t(c, f, h) {
        var w = 0;
        for (c = h ? q(c / g) : c >> 1, c += q(c / f); c > L * z >> 1; w += s)
          c = q(c / L);
        return q(w + (L + 1) * c / (c + S));
      }
      function o(c) {
        var f = [], h = c.length, w, b = 0, k = j, x = M, I, U, A, B, Q, C, O, Z, V;
        for (I = c.lastIndexOf(R), I < 0 && (I = 0), U = 0; U < I; ++U)
          c.charCodeAt(U) >= 128 && D("not-basic"), f.push(c.charCodeAt(U));
        for (A = I > 0 ? I + 1 : 0; A < h; ) {
          for (B = b, Q = 1, C = s; A >= h && D("invalid-input"), O = e(c.charCodeAt(A++)), (O >= s || O > q((l - b) / Q)) && D("overflow"), b += O * Q, Z = C <= x ? P : C >= x + z ? z : C - x, !(O < Z); C += s)
            V = s - Z, Q > q(l / V) && D("overflow"), Q *= V;
          w = f.length + 1, x = t(b - B, w, B == 0), q(b / w) > l - k && D("overflow"), k += q(b / w), b %= w, f.splice(b++, 0, k);
        }
        return Y(f);
      }
      function i(c) {
        var f, h, w, b, k, x, I, U, A, B, Q, C = [], O, Z, V, ee;
        for (c = X(c), O = c.length, f = j, h = 0, k = M, x = 0; x < O; ++x)
          Q = c[x], Q < 128 && C.push(T(Q));
        for (w = b = C.length, b && C.push(R); w < O; ) {
          for (I = l, x = 0; x < O; ++x)
            Q = c[x], Q >= f && Q < I && (I = Q);
          for (Z = w + 1, I - f > q((l - h) / Z) && D("overflow"), h += (I - f) * Z, f = I, x = 0; x < O; ++x)
            if (Q = c[x], Q < f && ++h > l && D("overflow"), Q == f) {
              for (U = h, A = s; B = A <= k ? P : A >= k + z ? z : A - k, !(U < B); A += s)
                ee = U - B, V = s - B, C.push(
                  T(r(B + ee % V, 0))
                ), U = q(ee / V);
              C.push(T(r(U, 0))), k = t(h, Z, w == b), h = 0, ++w;
            }
          ++h, ++f;
        }
        return C.join("");
      }
      function a(c) {
        return W(c, function(f) {
          return F.test(f) ? o(f.slice(4).toLowerCase()) : f;
        });
      }
      function u(c) {
        return W(c, function(f) {
          return N.test(f) ? "xn--" + i(f) : f;
        });
      }
      if (n = {
        /**
         * A string representing the current Punycode.js version number.
         * @memberOf punycode
         * @type String
         */
        version: "1.3.2",
        /**
         * An object of methods to convert from JavaScript's internal character
         * representation (UCS-2) to Unicode code points, and back.
         * @see <https://mathiasbynens.be/notes/javascript-encoding>
         * @memberOf punycode
         * @type Object
         */
        ucs2: {
          decode: X,
          encode: Y
        },
        decode: o,
        encode: i,
        toASCII: u,
        toUnicode: a
      }, y && p)
        if (v.exports == y)
          p.exports = n;
        else
          for (H in n)
            n.hasOwnProperty(H) && (y[H] = n[H]);
      else
        _.punycode = n;
    })(G);
  }(J, J.exports)), J.exports;
}
var re = { exports: {} };
/*!
 * URI.js - Mutating URLs
 * IPv6 Support
 *
 * Version: 1.19.11
 *
 * Author: Rodney Rehm
 * Web: http://medialize.github.io/URI.js/
 *
 * Licensed under
 *   MIT License http://www.opensource.org/licenses/mit-license
 *
 */
var oe;
function fe() {
  return oe || (oe = 1, function(v) {
    (function(d, _) {
      v.exports ? v.exports = _() : d.IPv6 = _(d);
    })(G, function(d) {
      var _ = d && d.IPv6;
      function y(m) {
        var n = m.toLowerCase(), l = n.split(":"), s = l.length, P = 8;
        l[0] === "" && l[1] === "" && l[2] === "" ? (l.shift(), l.shift()) : l[0] === "" && l[1] === "" ? l.shift() : l[s - 1] === "" && l[s - 2] === "" && l.pop(), s = l.length, l[s - 1].indexOf(".") !== -1 && (P = 7);
        var z;
        for (z = 0; z < s && l[z] !== ""; z++)
          ;
        if (z < P)
          for (l.splice(z, 1, "0000"); l.length < P; )
            l.splice(z, 0, "0000");
        for (var S, g = 0; g < P; g++) {
          S = l[g].split("");
          for (var M = 0; M < 3 && (S[0] === "0" && S.length > 1); M++)
            S.splice(0, 1);
          l[g] = S.join("");
        }
        var j = -1, R = 0, F = 0, N = -1, $ = !1;
        for (g = 0; g < P; g++)
          $ ? l[g] === "0" ? F += 1 : ($ = !1, F > R && (j = N, R = F)) : l[g] === "0" && ($ = !0, N = g, F = 1);
        F > R && (j = N, R = F), R > 1 && l.splice(j, R, ""), s = l.length;
        var E = "";
        for (l[0] === "" && (E = ":"), g = 0; g < s && (E += l[g], g !== s - 1); g++)
          E += ":";
        return l[s - 1] === "" && (E += ":"), E;
      }
      function p() {
        return d.IPv6 === this && (d.IPv6 = _), this;
      }
      return {
        best: y,
        noConflict: p
      };
    });
  }(re)), re.exports;
}
var te = { exports: {} };
/*!
 * URI.js - Mutating URLs
 * Second Level Domain (SLD) Support
 *
 * Version: 1.19.11
 *
 * Author: Rodney Rehm
 * Web: http://medialize.github.io/URI.js/
 *
 * Licensed under
 *   MIT License http://www.opensource.org/licenses/mit-license
 *
 */
var ie;
function le() {
  return ie || (ie = 1, function(v) {
    (function(d, _) {
      v.exports ? v.exports = _() : d.SecondLevelDomains = _(d);
    })(G, function(d) {
      var _ = d && d.SecondLevelDomains, y = {
        // list of known Second Level Domains
        // converted list of SLDs from https://github.com/gavingmiller/second-level-domains
        // ----
        // publicsuffix.org is more current and actually used by a couple of browsers internally.
        // downside is it also contains domains like "dyndns.org" - which is fine for the security
        // issues browser have to deal with (SOP for cookies, etc) - but is way overboard for URI.js
        // ----
        list: {
          ac: " com gov mil net org ",
          ae: " ac co gov mil name net org pro sch ",
          af: " com edu gov net org ",
          al: " com edu gov mil net org ",
          ao: " co ed gv it og pb ",
          ar: " com edu gob gov int mil net org tur ",
          at: " ac co gv or ",
          au: " asn com csiro edu gov id net org ",
          ba: " co com edu gov mil net org rs unbi unmo unsa untz unze ",
          bb: " biz co com edu gov info net org store tv ",
          bh: " biz cc com edu gov info net org ",
          bn: " com edu gov net org ",
          bo: " com edu gob gov int mil net org tv ",
          br: " adm adv agr am arq art ato b bio blog bmd cim cng cnt com coop ecn edu eng esp etc eti far flog fm fnd fot fst g12 ggf gov imb ind inf jor jus lel mat med mil mus net nom not ntr odo org ppg pro psc psi qsl rec slg srv tmp trd tur tv vet vlog wiki zlg ",
          bs: " com edu gov net org ",
          bz: " du et om ov rg ",
          ca: " ab bc mb nb nf nl ns nt nu on pe qc sk yk ",
          ck: " biz co edu gen gov info net org ",
          cn: " ac ah bj com cq edu fj gd gov gs gx gz ha hb he hi hl hn jl js jx ln mil net nm nx org qh sc sd sh sn sx tj tw xj xz yn zj ",
          co: " com edu gov mil net nom org ",
          cr: " ac c co ed fi go or sa ",
          cy: " ac biz com ekloges gov ltd name net org parliament press pro tm ",
          do: " art com edu gob gov mil net org sld web ",
          dz: " art asso com edu gov net org pol ",
          ec: " com edu fin gov info med mil net org pro ",
          eg: " com edu eun gov mil name net org sci ",
          er: " com edu gov ind mil net org rochest w ",
          es: " com edu gob nom org ",
          et: " biz com edu gov info name net org ",
          fj: " ac biz com info mil name net org pro ",
          fk: " ac co gov net nom org ",
          fr: " asso com f gouv nom prd presse tm ",
          gg: " co net org ",
          gh: " com edu gov mil org ",
          gn: " ac com gov net org ",
          gr: " com edu gov mil net org ",
          gt: " com edu gob ind mil net org ",
          gu: " com edu gov net org ",
          hk: " com edu gov idv net org ",
          hu: " 2000 agrar bolt casino city co erotica erotika film forum games hotel info ingatlan jogasz konyvelo lakas media news org priv reklam sex shop sport suli szex tm tozsde utazas video ",
          id: " ac co go mil net or sch web ",
          il: " ac co gov idf k12 muni net org ",
          in: " ac co edu ernet firm gen gov i ind mil net nic org res ",
          iq: " com edu gov i mil net org ",
          ir: " ac co dnssec gov i id net org sch ",
          it: " edu gov ",
          je: " co net org ",
          jo: " com edu gov mil name net org sch ",
          jp: " ac ad co ed go gr lg ne or ",
          ke: " ac co go info me mobi ne or sc ",
          kh: " com edu gov mil net org per ",
          ki: " biz com de edu gov info mob net org tel ",
          km: " asso com coop edu gouv k medecin mil nom notaires pharmaciens presse tm veterinaire ",
          kn: " edu gov net org ",
          kr: " ac busan chungbuk chungnam co daegu daejeon es gangwon go gwangju gyeongbuk gyeonggi gyeongnam hs incheon jeju jeonbuk jeonnam k kg mil ms ne or pe re sc seoul ulsan ",
          kw: " com edu gov net org ",
          ky: " com edu gov net org ",
          kz: " com edu gov mil net org ",
          lb: " com edu gov net org ",
          lk: " assn com edu gov grp hotel int ltd net ngo org sch soc web ",
          lr: " com edu gov net org ",
          lv: " asn com conf edu gov id mil net org ",
          ly: " com edu gov id med net org plc sch ",
          ma: " ac co gov m net org press ",
          mc: " asso tm ",
          me: " ac co edu gov its net org priv ",
          mg: " com edu gov mil nom org prd tm ",
          mk: " com edu gov inf name net org pro ",
          ml: " com edu gov net org presse ",
          mn: " edu gov org ",
          mo: " com edu gov net org ",
          mt: " com edu gov net org ",
          mv: " aero biz com coop edu gov info int mil museum name net org pro ",
          mw: " ac co com coop edu gov int museum net org ",
          mx: " com edu gob net org ",
          my: " com edu gov mil name net org sch ",
          nf: " arts com firm info net other per rec store web ",
          ng: " biz com edu gov mil mobi name net org sch ",
          ni: " ac co com edu gob mil net nom org ",
          np: " com edu gov mil net org ",
          nr: " biz com edu gov info net org ",
          om: " ac biz co com edu gov med mil museum net org pro sch ",
          pe: " com edu gob mil net nom org sld ",
          ph: " com edu gov i mil net ngo org ",
          pk: " biz com edu fam gob gok gon gop gos gov net org web ",
          pl: " art bialystok biz com edu gda gdansk gorzow gov info katowice krakow lodz lublin mil net ngo olsztyn org poznan pwr radom slupsk szczecin torun warszawa waw wroc wroclaw zgora ",
          pr: " ac biz com edu est gov info isla name net org pro prof ",
          ps: " com edu gov net org plo sec ",
          pw: " belau co ed go ne or ",
          ro: " arts com firm info nom nt org rec store tm www ",
          rs: " ac co edu gov in org ",
          sb: " com edu gov net org ",
          sc: " com edu gov net org ",
          sh: " co com edu gov net nom org ",
          sl: " com edu gov net org ",
          st: " co com consulado edu embaixada gov mil net org principe saotome store ",
          sv: " com edu gob org red ",
          sz: " ac co org ",
          tr: " av bbs bel biz com dr edu gen gov info k12 name net org pol tel tsk tv web ",
          tt: " aero biz cat co com coop edu gov info int jobs mil mobi museum name net org pro tel travel ",
          tw: " club com ebiz edu game gov idv mil net org ",
          mu: " ac co com gov net or org ",
          mz: " ac co edu gov org ",
          na: " co com ",
          nz: " ac co cri geek gen govt health iwi maori mil net org parliament school ",
          pa: " abo ac com edu gob ing med net nom org sld ",
          pt: " com edu gov int net nome org publ ",
          py: " com edu gov mil net org ",
          qa: " com edu gov mil net org ",
          re: " asso com nom ",
          ru: " ac adygeya altai amur arkhangelsk astrakhan bashkiria belgorod bir bryansk buryatia cbg chel chelyabinsk chita chukotka chuvashia com dagestan e-burg edu gov grozny int irkutsk ivanovo izhevsk jar joshkar-ola kalmykia kaluga kamchatka karelia kazan kchr kemerovo khabarovsk khakassia khv kirov koenig komi kostroma kranoyarsk kuban kurgan kursk lipetsk magadan mari mari-el marine mil mordovia mosreg msk murmansk nalchik net nnov nov novosibirsk nsk omsk orenburg org oryol penza perm pp pskov ptz rnd ryazan sakhalin samara saratov simbirsk smolensk spb stavropol stv surgut tambov tatarstan tom tomsk tsaritsyn tsk tula tuva tver tyumen udm udmurtia ulan-ude vladikavkaz vladimir vladivostok volgograd vologda voronezh vrn vyatka yakutia yamal yekaterinburg yuzhno-sakhalinsk ",
          rw: " ac co com edu gouv gov int mil net ",
          sa: " com edu gov med net org pub sch ",
          sd: " com edu gov info med net org tv ",
          se: " a ac b bd c d e f g h i k l m n o org p parti pp press r s t tm u w x y z ",
          sg: " com edu gov idn net org per ",
          sn: " art com edu gouv org perso univ ",
          sy: " com edu gov mil net news org ",
          th: " ac co go in mi net or ",
          tj: " ac biz co com edu go gov info int mil name net nic org test web ",
          tn: " agrinet com defense edunet ens fin gov ind info intl mincom nat net org perso rnrt rns rnu tourism ",
          tz: " ac co go ne or ",
          ua: " biz cherkassy chernigov chernovtsy ck cn co com crimea cv dn dnepropetrovsk donetsk dp edu gov if in ivano-frankivsk kh kharkov kherson khmelnitskiy kiev kirovograd km kr ks kv lg lugansk lutsk lviv me mk net nikolaev od odessa org pl poltava pp rovno rv sebastopol sumy te ternopil uzhgorod vinnica vn zaporizhzhe zhitomir zp zt ",
          ug: " ac co go ne or org sc ",
          uk: " ac bl british-library co cym gov govt icnet jet lea ltd me mil mod national-library-scotland nel net nhs nic nls org orgn parliament plc police sch scot soc ",
          us: " dni fed isa kids nsn ",
          uy: " com edu gub mil net org ",
          ve: " co com edu gob info mil net org web ",
          vi: " co com k12 net org ",
          vn: " ac biz com edu gov health info int name net org pro ",
          ye: " co com gov ltd me net org plc ",
          yu: " ac co edu gov org ",
          za: " ac agric alt bourse city co cybernet db edu gov grondar iaccess imt inca landesign law mil net ngo nis nom olivetti org pix school tm web ",
          zm: " ac co com edu gov net org sch ",
          // https://en.wikipedia.org/wiki/CentralNic#Second-level_domains
          com: "ar br cn de eu gb gr hu jpn kr no qc ru sa se uk us uy za ",
          net: "gb jp se uk ",
          org: "ae",
          de: "com "
        },
        // gorhill 2013-10-25: Using indexOf() instead Regexp(). Significant boost
        // in both performance and memory footprint. No initialization required.
        // http://jsperf.com/uri-js-sld-regex-vs-binary-search/4
        // Following methods use lastIndexOf() rather than array.split() in order
        // to avoid any memory allocations.
        has: function(p) {
          var m = p.lastIndexOf(".");
          if (m <= 0 || m >= p.length - 1)
            return !1;
          var n = p.lastIndexOf(".", m - 1);
          if (n <= 0 || n >= m - 1)
            return !1;
          var l = y.list[p.slice(m + 1)];
          return l ? l.indexOf(" " + p.slice(n + 1, m) + " ") >= 0 : !1;
        },
        is: function(p) {
          var m = p.lastIndexOf(".");
          if (m <= 0 || m >= p.length - 1)
            return !1;
          var n = p.lastIndexOf(".", m - 1);
          if (n >= 0)
            return !1;
          var l = y.list[p.slice(m + 1)];
          return l ? l.indexOf(" " + p.slice(0, m) + " ") >= 0 : !1;
        },
        get: function(p) {
          var m = p.lastIndexOf(".");
          if (m <= 0 || m >= p.length - 1)
            return null;
          var n = p.lastIndexOf(".", m - 1);
          if (n <= 0 || n >= m - 1)
            return null;
          var l = y.list[p.slice(m + 1)];
          return !l || l.indexOf(" " + p.slice(n + 1, m) + " ") < 0 ? null : p.slice(n + 1);
        },
        noConflict: function() {
          return d.SecondLevelDomains === this && (d.SecondLevelDomains = _), this;
        }
      };
      return y;
    });
  }(te)), te.exports;
}
/*!
 * URI.js - Mutating URLs
 *
 * Version: 1.19.11
 *
 * Author: Rodney Rehm
 * Web: http://medialize.github.io/URI.js/
 *
 * Licensed under
 *   MIT License http://www.opensource.org/licenses/mit-license
 *
 */
(function(v) {
  (function(d, _) {
    v.exports ? v.exports = _(ce(), fe(), le()) : d.URI = _(d.punycode, d.IPv6, d.SecondLevelDomains, d);
  })(G, function(d, _, y, p) {
    var m = p && p.URI;
    function n(e, r) {
      var t = arguments.length >= 1, o = arguments.length >= 2;
      if (!(this instanceof n))
        return t ? o ? new n(e, r) : new n(e) : new n();
      if (e === void 0) {
        if (t)
          throw new TypeError("undefined is not a valid argument for URI");
        typeof location < "u" ? e = location.href + "" : e = "";
      }
      if (e === null && t)
        throw new TypeError("null is not a valid argument for URI");
      return this.href(e), r !== void 0 ? this.absoluteTo(r) : this;
    }
    function l(e) {
      return /^[0-9]+$/.test(e);
    }
    n.version = "1.19.11";
    var s = n.prototype, P = Object.prototype.hasOwnProperty;
    function z(e) {
      return e.replace(/([.*+?^=!:${}()|[\]\/\\])/g, "\\$1");
    }
    function S(e) {
      return e === void 0 ? "Undefined" : String(Object.prototype.toString.call(e)).slice(8, -1);
    }
    function g(e) {
      return S(e) === "Array";
    }
    function M(e, r) {
      var t = {}, o, i;
      if (S(r) === "RegExp")
        t = null;
      else if (g(r))
        for (o = 0, i = r.length; o < i; o++)
          t[r[o]] = !0;
      else
        t[r] = !0;
      for (o = 0, i = e.length; o < i; o++) {
        var a = t && t[e[o]] !== void 0 || !t && r.test(e[o]);
        a && (e.splice(o, 1), i--, o--);
      }
      return e;
    }
    function j(e, r) {
      var t, o;
      if (g(r)) {
        for (t = 0, o = r.length; t < o; t++)
          if (!j(e, r[t]))
            return !1;
        return !0;
      }
      var i = S(r);
      for (t = 0, o = e.length; t < o; t++)
        if (i === "RegExp") {
          if (typeof e[t] == "string" && e[t].match(r))
            return !0;
        } else if (e[t] === r)
          return !0;
      return !1;
    }
    function R(e, r) {
      if (!g(e) || !g(r) || e.length !== r.length)
        return !1;
      e.sort(), r.sort();
      for (var t = 0, o = e.length; t < o; t++)
        if (e[t] !== r[t])
          return !1;
      return !0;
    }
    function F(e) {
      var r = /^\/+|\/+$/g;
      return e.replace(r, "");
    }
    n._parts = function() {
      return {
        protocol: null,
        username: null,
        password: null,
        hostname: null,
        urn: null,
        port: null,
        path: null,
        query: null,
        fragment: null,
        // state
        preventInvalidHostname: n.preventInvalidHostname,
        duplicateQueryParameters: n.duplicateQueryParameters,
        escapeQuerySpace: n.escapeQuerySpace
      };
    }, n.preventInvalidHostname = !1, n.duplicateQueryParameters = !1, n.escapeQuerySpace = !0, n.protocol_expression = /^[a-z][a-z0-9.+-]*$/i, n.idn_expression = /[^a-z0-9\._-]/i, n.punycode_expression = /(xn--)/i, n.ip4_expression = /^\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}$/, n.ip6_expression = /^\s*((([0-9A-Fa-f]{1,4}:){7}([0-9A-Fa-f]{1,4}|:))|(([0-9A-Fa-f]{1,4}:){6}(:[0-9A-Fa-f]{1,4}|((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){5}(((:[0-9A-Fa-f]{1,4}){1,2})|:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3})|:))|(([0-9A-Fa-f]{1,4}:){4}(((:[0-9A-Fa-f]{1,4}){1,3})|((:[0-9A-Fa-f]{1,4})?:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){3}(((:[0-9A-Fa-f]{1,4}){1,4})|((:[0-9A-Fa-f]{1,4}){0,2}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){2}(((:[0-9A-Fa-f]{1,4}){1,5})|((:[0-9A-Fa-f]{1,4}){0,3}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(([0-9A-Fa-f]{1,4}:){1}(((:[0-9A-Fa-f]{1,4}){1,6})|((:[0-9A-Fa-f]{1,4}){0,4}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:))|(:(((:[0-9A-Fa-f]{1,4}){1,7})|((:[0-9A-Fa-f]{1,4}){0,5}:((25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)(\.(25[0-5]|2[0-4]\d|1\d\d|[1-9]?\d)){3}))|:)))(%.+)?\s*$/, n.find_uri_expression = /\b((?:[a-z][\w-]+:(?:\/{1,3}|[a-z0-9%])|www\d{0,3}[.]|[a-z0-9.\-]+[.][a-z]{2,4}\/)(?:[^\s()<>]+|\(([^\s()<>]+|(\([^\s()<>]+\)))*\))+(?:\(([^\s()<>]+|(\([^\s()<>]+\)))*\)|[^\s`!()\[\]{};:'".,<>?«»“”‘’]))/ig, n.findUri = {
      // valid "scheme://" or "www."
      start: /\b(?:([a-z][a-z0-9.+-]*:\/\/)|www\.)/gi,
      // everything up to the next whitespace
      end: /[\s\r\n]|$/,
      // trim trailing punctuation captured by end RegExp
      trim: /[`!()\[\]{};:'".,<>?«»“”„‘’]+$/,
      // balanced parens inclusion (), [], {}, <>
      parens: /(\([^\)]*\)|\[[^\]]*\]|\{[^}]*\}|<[^>]*>)/g
    }, n.leading_whitespace_expression = /^[\x00-\x20\u00a0\u1680\u2000-\u200a\u2028\u2029\u202f\u205f\u3000\ufeff]+/, n.ascii_tab_whitespace = /[\u0009\u000A\u000D]+/g, n.defaultPorts = {
      http: "80",
      https: "443",
      ftp: "21",
      gopher: "70",
      ws: "80",
      wss: "443"
    }, n.hostProtocols = [
      "http",
      "https"
    ], n.invalid_hostname_characters = /[^a-zA-Z0-9\.\-:_]/, n.domAttributes = {
      a: "href",
      blockquote: "cite",
      link: "href",
      base: "href",
      script: "src",
      form: "action",
      img: "src",
      area: "href",
      iframe: "src",
      embed: "src",
      source: "src",
      track: "src",
      input: "src",
      // but only if type="image"
      audio: "src",
      video: "src"
    }, n.getDomAttribute = function(e) {
      if (!(!e || !e.nodeName)) {
        var r = e.nodeName.toLowerCase();
        if (!(r === "input" && e.type !== "image"))
          return n.domAttributes[r];
      }
    };
    function N(e) {
      return escape(e);
    }
    function $(e) {
      return encodeURIComponent(e).replace(/[!'()*]/g, N).replace(/\*/g, "%2A");
    }
    n.encode = $, n.decode = decodeURIComponent, n.iso8859 = function() {
      n.encode = escape, n.decode = unescape;
    }, n.unicode = function() {
      n.encode = $, n.decode = decodeURIComponent;
    }, n.characters = {
      pathname: {
        encode: {
          // RFC3986 2.1: For consistency, URI producers and normalizers should
          // use uppercase hexadecimal digits for all percent-encodings.
          expression: /%(24|26|2B|2C|3B|3D|3A|40)/ig,
          map: {
            // -._~!'()*
            "%24": "$",
            "%26": "&",
            "%2B": "+",
            "%2C": ",",
            "%3B": ";",
            "%3D": "=",
            "%3A": ":",
            "%40": "@"
          }
        },
        decode: {
          expression: /[\/\?#]/g,
          map: {
            "/": "%2F",
            "?": "%3F",
            "#": "%23"
          }
        }
      },
      reserved: {
        encode: {
          // RFC3986 2.1: For consistency, URI producers and normalizers should
          // use uppercase hexadecimal digits for all percent-encodings.
          expression: /%(21|23|24|26|27|28|29|2A|2B|2C|2F|3A|3B|3D|3F|40|5B|5D)/ig,
          map: {
            // gen-delims
            "%3A": ":",
            "%2F": "/",
            "%3F": "?",
            "%23": "#",
            "%5B": "[",
            "%5D": "]",
            "%40": "@",
            // sub-delims
            "%21": "!",
            "%24": "$",
            "%26": "&",
            "%27": "'",
            "%28": "(",
            "%29": ")",
            "%2A": "*",
            "%2B": "+",
            "%2C": ",",
            "%3B": ";",
            "%3D": "="
          }
        }
      },
      urnpath: {
        // The characters under `encode` are the characters called out by RFC 2141 as being acceptable
        // for usage in a URN. RFC2141 also calls out "-", ".", and "_" as acceptable characters, but
        // these aren't encoded by encodeURIComponent, so we don't have to call them out here. Also
        // note that the colon character is not featured in the encoding map; this is because URI.js
        // gives the colons in URNs semantic meaning as the delimiters of path segements, and so it
        // should not appear unencoded in a segment itself.
        // See also the note above about RFC3986 and capitalalized hex digits.
        encode: {
          expression: /%(21|24|27|28|29|2A|2B|2C|3B|3D|40)/ig,
          map: {
            "%21": "!",
            "%24": "$",
            "%27": "'",
            "%28": "(",
            "%29": ")",
            "%2A": "*",
            "%2B": "+",
            "%2C": ",",
            "%3B": ";",
            "%3D": "=",
            "%40": "@"
          }
        },
        // These characters are the characters called out by RFC2141 as "reserved" characters that
        // should never appear in a URN, plus the colon character (see note above).
        decode: {
          expression: /[\/\?#:]/g,
          map: {
            "/": "%2F",
            "?": "%3F",
            "#": "%23",
            ":": "%3A"
          }
        }
      }
    }, n.encodeQuery = function(e, r) {
      var t = n.encode(e + "");
      return r === void 0 && (r = n.escapeQuerySpace), r ? t.replace(/%20/g, "+") : t;
    }, n.decodeQuery = function(e, r) {
      e += "", r === void 0 && (r = n.escapeQuerySpace);
      try {
        return n.decode(r ? e.replace(/\+/g, "%20") : e);
      } catch {
        return e;
      }
    };
    var E = { encode: "encode", decode: "decode" }, L, q = function(e, r) {
      return function(t) {
        try {
          return n[r](t + "").replace(n.characters[e][r].expression, function(o) {
            return n.characters[e][r].map[o];
          });
        } catch {
          return t;
        }
      };
    };
    for (L in E)
      n[L + "PathSegment"] = q("pathname", E[L]), n[L + "UrnPathSegment"] = q("urnpath", E[L]);
    var T = function(e, r, t) {
      return function(o) {
        var i;
        t ? i = function(f) {
          return n[r](n[t](f));
        } : i = n[r];
        for (var a = (o + "").split(e), u = 0, c = a.length; u < c; u++)
          a[u] = i(a[u]);
        return a.join(e);
      };
    };
    n.decodePath = T("/", "decodePathSegment"), n.decodeUrnPath = T(":", "decodeUrnPathSegment"), n.recodePath = T("/", "encodePathSegment", "decode"), n.recodeUrnPath = T(":", "encodeUrnPathSegment", "decode"), n.encodeReserved = q("reserved", "encode"), n.parse = function(e, r) {
      var t;
      return r || (r = {
        preventInvalidHostname: n.preventInvalidHostname
      }), e = e.replace(n.leading_whitespace_expression, ""), e = e.replace(n.ascii_tab_whitespace, ""), t = e.indexOf("#"), t > -1 && (r.fragment = e.substring(t + 1) || null, e = e.substring(0, t)), t = e.indexOf("?"), t > -1 && (r.query = e.substring(t + 1) || null, e = e.substring(0, t)), e = e.replace(/^(https?|ftp|wss?)?:+[/\\]*/i, "$1://"), e = e.replace(/^[/\\]{2,}/i, "//"), e.substring(0, 2) === "//" ? (r.protocol = null, e = e.substring(2), e = n.parseAuthority(e, r)) : (t = e.indexOf(":"), t > -1 && (r.protocol = e.substring(0, t) || null, r.protocol && !r.protocol.match(n.protocol_expression) ? r.protocol = void 0 : e.substring(t + 1, t + 3).replace(/\\/g, "/") === "//" ? (e = e.substring(t + 3), e = n.parseAuthority(e, r)) : (e = e.substring(t + 1), r.urn = !0))), r.path = e, r;
    }, n.parseHost = function(e, r) {
      e || (e = ""), e = e.replace(/\\/g, "/");
      var t = e.indexOf("/"), o, i;
      if (t === -1 && (t = e.length), e.charAt(0) === "[")
        o = e.indexOf("]"), r.hostname = e.substring(1, o) || null, r.port = e.substring(o + 2, t) || null, r.port === "/" && (r.port = null);
      else {
        var a = e.indexOf(":"), u = e.indexOf("/"), c = e.indexOf(":", a + 1);
        c !== -1 && (u === -1 || c < u) ? (r.hostname = e.substring(0, t) || null, r.port = null) : (i = e.substring(0, t).split(":"), r.hostname = i[0] || null, r.port = i[1] || null);
      }
      return r.hostname && e.substring(t).charAt(0) !== "/" && (t++, e = "/" + e), r.preventInvalidHostname && n.ensureValidHostname(r.hostname, r.protocol), r.port && n.ensureValidPort(r.port), e.substring(t) || "/";
    }, n.parseAuthority = function(e, r) {
      return e = n.parseUserinfo(e, r), n.parseHost(e, r);
    }, n.parseUserinfo = function(e, r) {
      var t = e, o = e.indexOf("\\");
      o !== -1 && (e = e.replace(/\\/g, "/"));
      var i = e.indexOf("/"), a = e.lastIndexOf("@", i > -1 ? i : e.length - 1), u;
      return a > -1 && (i === -1 || a < i) ? (u = e.substring(0, a).split(":"), r.username = u[0] ? n.decode(u[0]) : null, u.shift(), r.password = u[0] ? n.decode(u.join(":")) : null, e = t.substring(a + 1)) : (r.username = null, r.password = null), e;
    }, n.parseQuery = function(e, r) {
      if (!e)
        return {};
      if (e = e.replace(/&+/g, "&").replace(/^\?*&*|&+$/g, ""), !e)
        return {};
      for (var t = {}, o = e.split("&"), i = o.length, a, u, c, f = 0; f < i; f++)
        a = o[f].split("="), u = n.decodeQuery(a.shift(), r), c = a.length ? n.decodeQuery(a.join("="), r) : null, u !== "__proto__" && (P.call(t, u) ? ((typeof t[u] == "string" || t[u] === null) && (t[u] = [t[u]]), t[u].push(c)) : t[u] = c);
      return t;
    }, n.build = function(e) {
      var r = "", t = !1;
      return e.protocol && (r += e.protocol + ":"), !e.urn && (r || e.hostname) && (r += "//", t = !0), r += n.buildAuthority(e) || "", typeof e.path == "string" && (e.path.charAt(0) !== "/" && t && (r += "/"), r += e.path), typeof e.query == "string" && e.query && (r += "?" + e.query), typeof e.fragment == "string" && e.fragment && (r += "#" + e.fragment), r;
    }, n.buildHost = function(e) {
      var r = "";
      if (e.hostname)
        n.ip6_expression.test(e.hostname) ? r += "[" + e.hostname + "]" : r += e.hostname;
      else
        return "";
      return e.port && (r += ":" + e.port), r;
    }, n.buildAuthority = function(e) {
      return n.buildUserinfo(e) + n.buildHost(e);
    }, n.buildUserinfo = function(e) {
      var r = "";
      return e.username && (r += n.encode(e.username)), e.password && (r += ":" + n.encode(e.password)), r && (r += "@"), r;
    }, n.buildQuery = function(e, r, t) {
      var o = "", i, a, u, c;
      for (a in e)
        if (a !== "__proto__" && P.call(e, a))
          if (g(e[a]))
            for (i = {}, u = 0, c = e[a].length; u < c; u++)
              e[a][u] !== void 0 && i[e[a][u] + ""] === void 0 && (o += "&" + n.buildQueryParameter(a, e[a][u], t), r !== !0 && (i[e[a][u] + ""] = !0));
          else
            e[a] !== void 0 && (o += "&" + n.buildQueryParameter(a, e[a], t));
      return o.substring(1);
    }, n.buildQueryParameter = function(e, r, t) {
      return n.encodeQuery(e, t) + (r !== null ? "=" + n.encodeQuery(r, t) : "");
    }, n.addQuery = function(e, r, t) {
      if (typeof r == "object")
        for (var o in r)
          P.call(r, o) && n.addQuery(e, o, r[o]);
      else if (typeof r == "string") {
        if (e[r] === void 0) {
          e[r] = t;
          return;
        } else
          typeof e[r] == "string" && (e[r] = [e[r]]);
        g(t) || (t = [t]), e[r] = (e[r] || []).concat(t);
      } else
        throw new TypeError("URI.addQuery() accepts an object, string as the name parameter");
    }, n.setQuery = function(e, r, t) {
      if (typeof r == "object")
        for (var o in r)
          P.call(r, o) && n.setQuery(e, o, r[o]);
      else if (typeof r == "string")
        e[r] = t === void 0 ? null : t;
      else
        throw new TypeError("URI.setQuery() accepts an object, string as the name parameter");
    }, n.removeQuery = function(e, r, t) {
      var o, i, a;
      if (g(r))
        for (o = 0, i = r.length; o < i; o++)
          e[r[o]] = void 0;
      else if (S(r) === "RegExp")
        for (a in e)
          r.test(a) && (e[a] = void 0);
      else if (typeof r == "object")
        for (a in r)
          P.call(r, a) && n.removeQuery(e, a, r[a]);
      else if (typeof r == "string")
        t !== void 0 ? S(t) === "RegExp" ? !g(e[r]) && t.test(e[r]) ? e[r] = void 0 : e[r] = M(e[r], t) : e[r] === String(t) && (!g(t) || t.length === 1) ? e[r] = void 0 : g(e[r]) && (e[r] = M(e[r], t)) : e[r] = void 0;
      else
        throw new TypeError("URI.removeQuery() accepts an object, string, RegExp as the first parameter");
    }, n.hasQuery = function(e, r, t, o) {
      switch (S(r)) {
        case "String":
          break;
        case "RegExp":
          for (var i in e)
            if (P.call(e, i) && r.test(i) && (t === void 0 || n.hasQuery(e, i, t)))
              return !0;
          return !1;
        case "Object":
          for (var a in r)
            if (P.call(r, a) && !n.hasQuery(e, a, r[a]))
              return !1;
          return !0;
        default:
          throw new TypeError("URI.hasQuery() accepts a string, regular expression or object as the name parameter");
      }
      switch (S(t)) {
        case "Undefined":
          return r in e;
        case "Boolean":
          var u = !!(g(e[r]) ? e[r].length : e[r]);
          return t === u;
        case "Function":
          return !!t(e[r], r, e);
        case "Array":
          if (!g(e[r]))
            return !1;
          var c = o ? j : R;
          return c(e[r], t);
        case "RegExp":
          return g(e[r]) ? o ? j(e[r], t) : !1 : !!(e[r] && e[r].match(t));
        case "Number":
          t = String(t);
        case "String":
          return g(e[r]) ? o ? j(e[r], t) : !1 : e[r] === t;
        default:
          throw new TypeError("URI.hasQuery() accepts undefined, boolean, string, number, RegExp, Function as the value parameter");
      }
    }, n.joinPaths = function() {
      for (var e = [], r = [], t = 0, o = 0; o < arguments.length; o++) {
        var i = new n(arguments[o]);
        e.push(i);
        for (var a = i.segment(), u = 0; u < a.length; u++)
          typeof a[u] == "string" && r.push(a[u]), a[u] && t++;
      }
      if (!r.length || !t)
        return new n("");
      var c = new n("").segment(r);
      return (e[0].path() === "" || e[0].path().slice(0, 1) === "/") && c.path("/" + c.path()), c.normalize();
    }, n.commonPath = function(e, r) {
      var t = Math.min(e.length, r.length), o;
      for (o = 0; o < t; o++)
        if (e.charAt(o) !== r.charAt(o)) {
          o--;
          break;
        }
      return o < 1 ? e.charAt(0) === r.charAt(0) && e.charAt(0) === "/" ? "/" : "" : ((e.charAt(o) !== "/" || r.charAt(o) !== "/") && (o = e.substring(0, o).lastIndexOf("/")), e.substring(0, o + 1));
    }, n.withinString = function(e, r, t) {
      t || (t = {});
      var o = t.start || n.findUri.start, i = t.end || n.findUri.end, a = t.trim || n.findUri.trim, u = t.parens || n.findUri.parens, c = /[a-z0-9-]=["']?$/i;
      for (o.lastIndex = 0; ; ) {
        var f = o.exec(e);
        if (!f)
          break;
        var h = f.index;
        if (t.ignoreHtml) {
          var w = e.slice(Math.max(h - 3, 0), h);
          if (w && c.test(w))
            continue;
        }
        for (var b = h + e.slice(h).search(i), k = e.slice(h, b), x = -1; ; ) {
          var I = u.exec(k);
          if (!I)
            break;
          var U = I.index + I[0].length;
          x = Math.max(x, U);
        }
        if (x > -1 ? k = k.slice(0, x) + k.slice(x).replace(a, "") : k = k.replace(a, ""), !(k.length <= f[0].length) && !(t.ignore && t.ignore.test(k))) {
          b = h + k.length;
          var A = r(k, h, b, e);
          if (A === void 0) {
            o.lastIndex = b;
            continue;
          }
          A = String(A), e = e.slice(0, h) + A + e.slice(b), o.lastIndex = h + A.length;
        }
      }
      return o.lastIndex = 0, e;
    }, n.ensureValidHostname = function(e, r) {
      var t = !!e, o = !!r, i = !1;
      if (o && (i = j(n.hostProtocols, r)), i && !t)
        throw new TypeError("Hostname cannot be empty, if protocol is " + r);
      if (e && e.match(n.invalid_hostname_characters)) {
        if (!d)
          throw new TypeError('Hostname "' + e + '" contains characters other than [A-Z0-9.-:_] and Punycode.js is not available');
        if (d.toASCII(e).match(n.invalid_hostname_characters))
          throw new TypeError('Hostname "' + e + '" contains characters other than [A-Z0-9.-:_]');
      }
    }, n.ensureValidPort = function(e) {
      if (e) {
        var r = Number(e);
        if (!(l(r) && r > 0 && r < 65536))
          throw new TypeError('Port "' + e + '" is not a valid port');
      }
    }, n.noConflict = function(e) {
      if (e) {
        var r = {
          URI: this.noConflict()
        };
        return p.URITemplate && typeof p.URITemplate.noConflict == "function" && (r.URITemplate = p.URITemplate.noConflict()), p.IPv6 && typeof p.IPv6.noConflict == "function" && (r.IPv6 = p.IPv6.noConflict()), p.SecondLevelDomains && typeof p.SecondLevelDomains.noConflict == "function" && (r.SecondLevelDomains = p.SecondLevelDomains.noConflict()), r;
      } else
        p.URI === this && (p.URI = m);
      return this;
    }, s.build = function(e) {
      return e === !0 ? this._deferred_build = !0 : (e === void 0 || this._deferred_build) && (this._string = n.build(this._parts), this._deferred_build = !1), this;
    }, s.clone = function() {
      return new n(this);
    }, s.valueOf = s.toString = function() {
      return this.build(!1)._string;
    };
    function H(e) {
      return function(r, t) {
        return r === void 0 ? this._parts[e] || "" : (this._parts[e] = r || null, this.build(!t), this);
      };
    }
    function D(e, r) {
      return function(t, o) {
        return t === void 0 ? this._parts[e] || "" : (t !== null && (t = t + "", t.charAt(0) === r && (t = t.substring(1))), this._parts[e] = t, this.build(!o), this);
      };
    }
    s.protocol = H("protocol"), s.username = H("username"), s.password = H("password"), s.hostname = H("hostname"), s.port = H("port"), s.query = D("query", "?"), s.fragment = D("fragment", "#"), s.search = function(e, r) {
      var t = this.query(e, r);
      return typeof t == "string" && t.length ? "?" + t : t;
    }, s.hash = function(e, r) {
      var t = this.fragment(e, r);
      return typeof t == "string" && t.length ? "#" + t : t;
    }, s.pathname = function(e, r) {
      if (e === void 0 || e === !0) {
        var t = this._parts.path || (this._parts.hostname ? "/" : "");
        return e ? (this._parts.urn ? n.decodeUrnPath : n.decodePath)(t) : t;
      } else
        return this._parts.urn ? this._parts.path = e ? n.recodeUrnPath(e) : "" : this._parts.path = e ? n.recodePath(e) : "/", this.build(!r), this;
    }, s.path = s.pathname, s.href = function(e, r) {
      var t;
      if (e === void 0)
        return this.toString();
      this._string = "", this._parts = n._parts();
      var o = e instanceof n, i = typeof e == "object" && (e.hostname || e.path || e.pathname);
      if (e.nodeName) {
        var a = n.getDomAttribute(e);
        e = e[a] || "", i = !1;
      }
      if (!o && i && e.pathname !== void 0 && (e = e.toString()), typeof e == "string" || e instanceof String)
        this._parts = n.parse(String(e), this._parts);
      else if (o || i) {
        var u = o ? e._parts : e;
        for (t in u)
          t !== "query" && P.call(this._parts, t) && (this._parts[t] = u[t]);
        u.query && this.query(u.query, !1);
      } else
        throw new TypeError("invalid input");
      return this.build(!r), this;
    }, s.is = function(e) {
      var r = !1, t = !1, o = !1, i = !1, a = !1, u = !1, c = !1, f = !this._parts.urn;
      switch (this._parts.hostname && (f = !1, t = n.ip4_expression.test(this._parts.hostname), o = n.ip6_expression.test(this._parts.hostname), r = t || o, i = !r, a = i && y && y.has(this._parts.hostname), u = i && n.idn_expression.test(this._parts.hostname), c = i && n.punycode_expression.test(this._parts.hostname)), e.toLowerCase()) {
        case "relative":
          return f;
        case "absolute":
          return !f;
        case "domain":
        case "name":
          return i;
        case "sld":
          return a;
        case "ip":
          return r;
        case "ip4":
        case "ipv4":
        case "inet4":
          return t;
        case "ip6":
        case "ipv6":
        case "inet6":
          return o;
        case "idn":
          return u;
        case "url":
          return !this._parts.urn;
        case "urn":
          return !!this._parts.urn;
        case "punycode":
          return c;
      }
      return null;
    };
    var K = s.protocol, W = s.port, X = s.hostname;
    s.protocol = function(e, r) {
      if (e && (e = e.replace(/:(\/\/)?$/, ""), !e.match(n.protocol_expression)))
        throw new TypeError('Protocol "' + e + `" contains characters other than [A-Z0-9.+-] or doesn't start with [A-Z]`);
      return K.call(this, e, r);
    }, s.scheme = s.protocol, s.port = function(e, r) {
      return this._parts.urn ? e === void 0 ? "" : this : (e !== void 0 && (e === 0 && (e = null), e && (e += "", e.charAt(0) === ":" && (e = e.substring(1)), n.ensureValidPort(e))), W.call(this, e, r));
    }, s.hostname = function(e, r) {
      if (this._parts.urn)
        return e === void 0 ? "" : this;
      if (e !== void 0) {
        var t = { preventInvalidHostname: this._parts.preventInvalidHostname }, o = n.parseHost(e, t);
        if (o !== "/")
          throw new TypeError('Hostname "' + e + '" contains characters other than [A-Z0-9.-]');
        e = t.hostname, this._parts.preventInvalidHostname && n.ensureValidHostname(e, this._parts.protocol);
      }
      return X.call(this, e, r);
    }, s.origin = function(e, r) {
      if (this._parts.urn)
        return e === void 0 ? "" : this;
      if (e === void 0) {
        var t = this.protocol(), o = this.authority();
        return o ? (t ? t + "://" : "") + this.authority() : "";
      } else {
        var i = n(e);
        return this.protocol(i.protocol()).authority(i.authority()).build(!r), this;
      }
    }, s.host = function(e, r) {
      if (this._parts.urn)
        return e === void 0 ? "" : this;
      if (e === void 0)
        return this._parts.hostname ? n.buildHost(this._parts) : "";
      var t = n.parseHost(e, this._parts);
      if (t !== "/")
        throw new TypeError('Hostname "' + e + '" contains characters other than [A-Z0-9.-]');
      return this.build(!r), this;
    }, s.authority = function(e, r) {
      if (this._parts.urn)
        return e === void 0 ? "" : this;
      if (e === void 0)
        return this._parts.hostname ? n.buildAuthority(this._parts) : "";
      var t = n.parseAuthority(e, this._parts);
      if (t !== "/")
        throw new TypeError('Hostname "' + e + '" contains characters other than [A-Z0-9.-]');
      return this.build(!r), this;
    }, s.userinfo = function(e, r) {
      if (this._parts.urn)
        return e === void 0 ? "" : this;
      if (e === void 0) {
        var t = n.buildUserinfo(this._parts);
        return t && t.substring(0, t.length - 1);
      } else
        return e[e.length - 1] !== "@" && (e += "@"), n.parseUserinfo(e, this._parts), this.build(!r), this;
    }, s.resource = function(e, r) {
      var t;
      return e === void 0 ? this.path() + this.search() + this.hash() : (t = n.parse(e), this._parts.path = t.path, this._parts.query = t.query, this._parts.fragment = t.fragment, this.build(!r), this);
    }, s.subdomain = function(e, r) {
      if (this._parts.urn)
        return e === void 0 ? "" : this;
      if (e === void 0) {
        if (!this._parts.hostname || this.is("IP"))
          return "";
        var t = this._parts.hostname.length - this.domain().length - 1;
        return this._parts.hostname.substring(0, t) || "";
      } else {
        var o = this._parts.hostname.length - this.domain().length, i = this._parts.hostname.substring(0, o), a = new RegExp("^" + z(i));
        if (e && e.charAt(e.length - 1) !== "." && (e += "."), e.indexOf(":") !== -1)
          throw new TypeError("Domains cannot contain colons");
        return e && n.ensureValidHostname(e, this._parts.protocol), this._parts.hostname = this._parts.hostname.replace(a, e), this.build(!r), this;
      }
    }, s.domain = function(e, r) {
      if (this._parts.urn)
        return e === void 0 ? "" : this;
      if (typeof e == "boolean" && (r = e, e = void 0), e === void 0) {
        if (!this._parts.hostname || this.is("IP"))
          return "";
        var t = this._parts.hostname.match(/\./g);
        if (t && t.length < 2)
          return this._parts.hostname;
        var o = this._parts.hostname.length - this.tld(r).length - 1;
        return o = this._parts.hostname.lastIndexOf(".", o - 1) + 1, this._parts.hostname.substring(o) || "";
      } else {
        if (!e)
          throw new TypeError("cannot set domain empty");
        if (e.indexOf(":") !== -1)
          throw new TypeError("Domains cannot contain colons");
        if (n.ensureValidHostname(e, this._parts.protocol), !this._parts.hostname || this.is("IP"))
          this._parts.hostname = e;
        else {
          var i = new RegExp(z(this.domain()) + "$");
          this._parts.hostname = this._parts.hostname.replace(i, e);
        }
        return this.build(!r), this;
      }
    }, s.tld = function(e, r) {
      if (this._parts.urn)
        return e === void 0 ? "" : this;
      if (typeof e == "boolean" && (r = e, e = void 0), e === void 0) {
        if (!this._parts.hostname || this.is("IP"))
          return "";
        var t = this._parts.hostname.lastIndexOf("."), o = this._parts.hostname.substring(t + 1);
        return r !== !0 && y && y.list[o.toLowerCase()] && y.get(this._parts.hostname) || o;
      } else {
        var i;
        if (e)
          if (e.match(/[^a-zA-Z0-9-]/))
            if (y && y.is(e))
              i = new RegExp(z(this.tld()) + "$"), this._parts.hostname = this._parts.hostname.replace(i, e);
            else
              throw new TypeError('TLD "' + e + '" contains characters other than [A-Z0-9]');
          else {
            if (!this._parts.hostname || this.is("IP"))
              throw new ReferenceError("cannot set TLD on non-domain host");
            i = new RegExp(z(this.tld()) + "$"), this._parts.hostname = this._parts.hostname.replace(i, e);
          }
        else
          throw new TypeError("cannot set TLD empty");
        return this.build(!r), this;
      }
    }, s.directory = function(e, r) {
      if (this._parts.urn)
        return e === void 0 ? "" : this;
      if (e === void 0 || e === !0) {
        if (!this._parts.path && !this._parts.hostname)
          return "";
        if (this._parts.path === "/")
          return "/";
        var t = this._parts.path.length - this.filename().length - 1, o = this._parts.path.substring(0, t) || (this._parts.hostname ? "/" : "");
        return e ? n.decodePath(o) : o;
      } else {
        var i = this._parts.path.length - this.filename().length, a = this._parts.path.substring(0, i), u = new RegExp("^" + z(a));
        return this.is("relative") || (e || (e = "/"), e.charAt(0) !== "/" && (e = "/" + e)), e && e.charAt(e.length - 1) !== "/" && (e += "/"), e = n.recodePath(e), this._parts.path = this._parts.path.replace(u, e), this.build(!r), this;
      }
    }, s.filename = function(e, r) {
      if (this._parts.urn)
        return e === void 0 ? "" : this;
      if (typeof e != "string") {
        if (!this._parts.path || this._parts.path === "/")
          return "";
        var t = this._parts.path.lastIndexOf("/"), o = this._parts.path.substring(t + 1);
        return e ? n.decodePathSegment(o) : o;
      } else {
        var i = !1;
        e.charAt(0) === "/" && (e = e.substring(1)), e.match(/\.?\//) && (i = !0);
        var a = new RegExp(z(this.filename()) + "$");
        return e = n.recodePath(e), this._parts.path = this._parts.path.replace(a, e), i ? this.normalizePath(r) : this.build(!r), this;
      }
    }, s.suffix = function(e, r) {
      if (this._parts.urn)
        return e === void 0 ? "" : this;
      if (e === void 0 || e === !0) {
        if (!this._parts.path || this._parts.path === "/")
          return "";
        var t = this.filename(), o = t.lastIndexOf("."), i, a;
        return o === -1 ? "" : (i = t.substring(o + 1), a = /^[a-z0-9%]+$/i.test(i) ? i : "", e ? n.decodePathSegment(a) : a);
      } else {
        e.charAt(0) === "." && (e = e.substring(1));
        var u = this.suffix(), c;
        if (u)
          e ? c = new RegExp(z(u) + "$") : c = new RegExp(z("." + u) + "$");
        else {
          if (!e)
            return this;
          this._parts.path += "." + n.recodePath(e);
        }
        return c && (e = n.recodePath(e), this._parts.path = this._parts.path.replace(c, e)), this.build(!r), this;
      }
    }, s.segment = function(e, r, t) {
      var o = this._parts.urn ? ":" : "/", i = this.path(), a = i.substring(0, 1) === "/", u = i.split(o);
      if (e !== void 0 && typeof e != "number" && (t = r, r = e, e = void 0), e !== void 0 && typeof e != "number")
        throw new Error('Bad segment "' + e + '", must be 0-based integer');
      if (a && u.shift(), e < 0 && (e = Math.max(u.length + e, 0)), r === void 0)
        return e === void 0 ? u : u[e];
      if (e === null || u[e] === void 0)
        if (g(r)) {
          u = [];
          for (var c = 0, f = r.length; c < f; c++)
            !r[c].length && (!u.length || !u[u.length - 1].length) || (u.length && !u[u.length - 1].length && u.pop(), u.push(F(r[c])));
        } else
          (r || typeof r == "string") && (r = F(r), u[u.length - 1] === "" ? u[u.length - 1] = r : u.push(r));
      else
        r ? u[e] = F(r) : u.splice(e, 1);
      return a && u.unshift(""), this.path(u.join(o), t);
    }, s.segmentCoded = function(e, r, t) {
      var o, i, a;
      if (typeof e != "number" && (t = r, r = e, e = void 0), r === void 0) {
        if (o = this.segment(e, r, t), !g(o))
          o = o !== void 0 ? n.decode(o) : void 0;
        else
          for (i = 0, a = o.length; i < a; i++)
            o[i] = n.decode(o[i]);
        return o;
      }
      if (!g(r))
        r = typeof r == "string" || r instanceof String ? n.encode(r) : r;
      else
        for (i = 0, a = r.length; i < a; i++)
          r[i] = n.encode(r[i]);
      return this.segment(e, r, t);
    };
    var Y = s.query;
    return s.query = function(e, r) {
      if (e === !0)
        return n.parseQuery(this._parts.query, this._parts.escapeQuerySpace);
      if (typeof e == "function") {
        var t = n.parseQuery(this._parts.query, this._parts.escapeQuerySpace), o = e.call(this, t);
        return this._parts.query = n.buildQuery(o || t, this._parts.duplicateQueryParameters, this._parts.escapeQuerySpace), this.build(!r), this;
      } else
        return e !== void 0 && typeof e != "string" ? (this._parts.query = n.buildQuery(e, this._parts.duplicateQueryParameters, this._parts.escapeQuerySpace), this.build(!r), this) : Y.call(this, e, r);
    }, s.setQuery = function(e, r, t) {
      var o = n.parseQuery(this._parts.query, this._parts.escapeQuerySpace);
      if (typeof e == "string" || e instanceof String)
        o[e] = r !== void 0 ? r : null;
      else if (typeof e == "object")
        for (var i in e)
          P.call(e, i) && (o[i] = e[i]);
      else
        throw new TypeError("URI.addQuery() accepts an object, string as the name parameter");
      return this._parts.query = n.buildQuery(o, this._parts.duplicateQueryParameters, this._parts.escapeQuerySpace), typeof e != "string" && (t = r), this.build(!t), this;
    }, s.addQuery = function(e, r, t) {
      var o = n.parseQuery(this._parts.query, this._parts.escapeQuerySpace);
      return n.addQuery(o, e, r === void 0 ? null : r), this._parts.query = n.buildQuery(o, this._parts.duplicateQueryParameters, this._parts.escapeQuerySpace), typeof e != "string" && (t = r), this.build(!t), this;
    }, s.removeQuery = function(e, r, t) {
      var o = n.parseQuery(this._parts.query, this._parts.escapeQuerySpace);
      return n.removeQuery(o, e, r), this._parts.query = n.buildQuery(o, this._parts.duplicateQueryParameters, this._parts.escapeQuerySpace), typeof e != "string" && (t = r), this.build(!t), this;
    }, s.hasQuery = function(e, r, t) {
      var o = n.parseQuery(this._parts.query, this._parts.escapeQuerySpace);
      return n.hasQuery(o, e, r, t);
    }, s.setSearch = s.setQuery, s.addSearch = s.addQuery, s.removeSearch = s.removeQuery, s.hasSearch = s.hasQuery, s.normalize = function() {
      return this._parts.urn ? this.normalizeProtocol(!1).normalizePath(!1).normalizeQuery(!1).normalizeFragment(!1).build() : this.normalizeProtocol(!1).normalizeHostname(!1).normalizePort(!1).normalizePath(!1).normalizeQuery(!1).normalizeFragment(!1).build();
    }, s.normalizeProtocol = function(e) {
      return typeof this._parts.protocol == "string" && (this._parts.protocol = this._parts.protocol.toLowerCase(), this.build(!e)), this;
    }, s.normalizeHostname = function(e) {
      return this._parts.hostname && (this.is("IDN") && d ? this._parts.hostname = d.toASCII(this._parts.hostname) : this.is("IPv6") && _ && (this._parts.hostname = _.best(this._parts.hostname)), this._parts.hostname = this._parts.hostname.toLowerCase(), this.build(!e)), this;
    }, s.normalizePort = function(e) {
      return typeof this._parts.protocol == "string" && this._parts.port === n.defaultPorts[this._parts.protocol] && (this._parts.port = null, this.build(!e)), this;
    }, s.normalizePath = function(e) {
      var r = this._parts.path;
      if (!r)
        return this;
      if (this._parts.urn)
        return this._parts.path = n.recodeUrnPath(this._parts.path), this.build(!e), this;
      if (this._parts.path === "/")
        return this;
      r = n.recodePath(r);
      var t, o = "", i, a;
      for (r.charAt(0) !== "/" && (t = !0, r = "/" + r), (r.slice(-3) === "/.." || r.slice(-2) === "/.") && (r += "/"), r = r.replace(/(\/(\.\/)+)|(\/\.$)/g, "/").replace(/\/{2,}/g, "/"), t && (o = r.substring(1).match(/^(\.\.\/)+/) || "", o && (o = o[0])); i = r.search(/\/\.\.(\/|$)/), i !== -1; ) {
        if (i === 0) {
          r = r.substring(3);
          continue;
        }
        a = r.substring(0, i).lastIndexOf("/"), a === -1 && (a = i), r = r.substring(0, a) + r.substring(i + 3);
      }
      return t && this.is("relative") && (r = o + r.substring(1)), this._parts.path = r, this.build(!e), this;
    }, s.normalizePathname = s.normalizePath, s.normalizeQuery = function(e) {
      return typeof this._parts.query == "string" && (this._parts.query.length ? this.query(n.parseQuery(this._parts.query, this._parts.escapeQuerySpace)) : this._parts.query = null, this.build(!e)), this;
    }, s.normalizeFragment = function(e) {
      return this._parts.fragment || (this._parts.fragment = null, this.build(!e)), this;
    }, s.normalizeSearch = s.normalizeQuery, s.normalizeHash = s.normalizeFragment, s.iso8859 = function() {
      var e = n.encode, r = n.decode;
      n.encode = escape, n.decode = decodeURIComponent;
      try {
        this.normalize();
      } finally {
        n.encode = e, n.decode = r;
      }
      return this;
    }, s.unicode = function() {
      var e = n.encode, r = n.decode;
      n.encode = $, n.decode = unescape;
      try {
        this.normalize();
      } finally {
        n.encode = e, n.decode = r;
      }
      return this;
    }, s.readable = function() {
      var e = this.clone();
      e.username("").password("").normalize();
      var r = "";
      if (e._parts.protocol && (r += e._parts.protocol + "://"), e._parts.hostname && (e.is("punycode") && d ? (r += d.toUnicode(e._parts.hostname), e._parts.port && (r += ":" + e._parts.port)) : r += e.host()), e._parts.hostname && e._parts.path && e._parts.path.charAt(0) !== "/" && (r += "/"), r += e.path(!0), e._parts.query) {
        for (var t = "", o = 0, i = e._parts.query.split("&"), a = i.length; o < a; o++) {
          var u = (i[o] || "").split("=");
          t += "&" + n.decodeQuery(u[0], this._parts.escapeQuerySpace).replace(/&/g, "%26"), u[1] !== void 0 && (t += "=" + n.decodeQuery(u[1], this._parts.escapeQuerySpace).replace(/&/g, "%26"));
        }
        r += "?" + t.substring(1);
      }
      return r += n.decodeQuery(e.hash(), !0), r;
    }, s.absoluteTo = function(e) {
      var r = this.clone(), t = ["protocol", "username", "password", "hostname", "port"], o, i, a;
      if (this._parts.urn)
        throw new Error("URNs do not have any generally defined hierarchical components");
      if (e instanceof n || (e = new n(e)), r._parts.protocol || (r._parts.protocol = e._parts.protocol, this._parts.hostname))
        return r;
      for (i = 0; a = t[i]; i++)
        r._parts[a] = e._parts[a];
      return r._parts.path ? (r._parts.path.substring(-2) === ".." && (r._parts.path += "/"), r.path().charAt(0) !== "/" && (o = e.directory(), o = o || (e.path().indexOf("/") === 0 ? "/" : ""), r._parts.path = (o ? o + "/" : "") + r._parts.path, r.normalizePath())) : (r._parts.path = e._parts.path, r._parts.query || (r._parts.query = e._parts.query)), r.build(), r;
    }, s.relativeTo = function(e) {
      var r = this.clone().normalize(), t, o, i, a, u;
      if (r._parts.urn)
        throw new Error("URNs do not have any generally defined hierarchical components");
      if (e = new n(e).normalize(), t = r._parts, o = e._parts, a = r.path(), u = e.path(), a.charAt(0) !== "/")
        throw new Error("URI is already relative");
      if (u.charAt(0) !== "/")
        throw new Error("Cannot calculate a URI relative to another relative URI");
      if (t.protocol === o.protocol && (t.protocol = null), t.username !== o.username || t.password !== o.password || t.protocol !== null || t.username !== null || t.password !== null)
        return r.build();
      if (t.hostname === o.hostname && t.port === o.port)
        t.hostname = null, t.port = null;
      else
        return r.build();
      if (a === u)
        return t.path = "", r.build();
      if (i = n.commonPath(a, u), !i)
        return r.build();
      var c = o.path.substring(i.length).replace(/[^\/]*$/, "").replace(/.*?\//g, "../");
      return t.path = c + t.path.substring(i.length) || "./", r.build();
    }, s.equals = function(e) {
      var r = this.clone(), t = new n(e), o = {}, i = {}, a = {}, u, c, f;
      if (r.normalize(), t.normalize(), r.toString() === t.toString())
        return !0;
      if (u = r.query(), c = t.query(), r.query(""), t.query(""), r.toString() !== t.toString() || u.length !== c.length)
        return !1;
      o = n.parseQuery(u, this._parts.escapeQuerySpace), i = n.parseQuery(c, this._parts.escapeQuerySpace);
      for (f in o)
        if (P.call(o, f)) {
          if (g(o[f])) {
            if (!R(o[f], i[f]))
              return !1;
          } else if (o[f] !== i[f])
            return !1;
          a[f] = !0;
        }
      for (f in i)
        if (P.call(i, f) && !a[f])
          return !1;
      return !0;
    }, s.preventInvalidHostname = function(e) {
      return this._parts.preventInvalidHostname = !!e, this;
    }, s.duplicateQueryParameters = function(e) {
      return this._parts.duplicateQueryParameters = !!e, this;
    }, s.escapeQuerySpace = function(e) {
      return this._parts.escapeQuerySpace = !!e, this;
    }, n;
  });
})(ae);
var he = ae.exports;
const pe = /* @__PURE__ */ ue(he), se = [
  "zh-tw",
  "zh-cn",
  "zh-sg",
  "zh-hant",
  "zh-hans",
  "zh-my",
  "zh-mo",
  "zh-hk",
  "zh",
  "zh-reset"
];
function ge(v) {
  let d = v.value;
  de(d);
}
function de(v) {
  let d = window.location.href, _ = new pe(d);
  const y = _.segment().filter((n) => n !== ""), p = y[0], m = y[y.length - 1];
  !wpc_switcher_use_permalink_type || wpc_switcher_use_permalink_type == "0" ? (_.removeQuery("variant"), v && _.addQuery("variant", v)) : wpc_switcher_use_permalink_type == "1" ? (se.includes(m) ? _.segment(y.length - 1, v) : (y.push(v), _.segment(y))) : wpc_switcher_use_permalink_type == "2" && (se.includes(p) ? _.segment(0, v) : (y.unshift(v), _.segment(y))), window.location.href = _.toString();
}
export {
  ge as wpccRedirectToPage,
  de as wpccRedirectToVariant
};
