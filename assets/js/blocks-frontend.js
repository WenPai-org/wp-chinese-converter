document.addEventListener("DOMContentLoaded", function () {
  initLanguageSwitchers();
  initConversionStatus();
  initNoConversionBlocks();
});

function initLanguageSwitchers() {
  const switchers = document.querySelectorAll(".wpcc-language-switcher");

  switchers.forEach(function (switcher) {
    const placeholder = switcher.querySelector(".wpcc-switcher-placeholder");
    if (placeholder) {
      const displayStyle =
        placeholder.getAttribute("data-display-style") || "horizontal";
      const showNoConversion =
        placeholder.getAttribute("data-show-no-conversion") === "true";
      const enabledLanguages = JSON.parse(
        placeholder.getAttribute("data-enabled-languages") || "[]",
      );
      const customNoConversionLabel =
        placeholder.getAttribute("data-custom-no-conversion-label") || "";
      const sortOrder =
        placeholder.getAttribute("data-sort-order") || "default";
      const openInNewWindow =
        placeholder.getAttribute("data-open-in-new-window") === "true";
      const showLanguageCode =
        placeholder.getAttribute("data-show-language-code") === "true";
      const customLabels = JSON.parse(
        placeholder.getAttribute("data-custom-labels") || "{}",
      );

      renderLanguageSwitcher(
        placeholder,
        displayStyle,
        showNoConversion,
        enabledLanguages,
        customNoConversionLabel,
        sortOrder,
        openInNewWindow,
        showLanguageCode,
        customLabels,
      );
    }
  });
}

function renderLanguageSwitcher(
  placeholder,
  displayStyle,
  showNoConversion,
  enabledLanguages,
  customNoConversionLabel,
  sortOrder,
  openInNewWindow,
  showLanguageCode,
  customLabels,
) {
  const currentLang = getCurrentLanguage();
  const allLanguages = getAvailableLanguages();

  let availableLanguages = allLanguages;
  if (enabledLanguages && enabledLanguages.length > 0) {
    availableLanguages = allLanguages.filter((lang) =>
      enabledLanguages.includes(lang.code),
    );
  }

  availableLanguages = sortLanguages(availableLanguages, sortOrder);

  const getLanguageLabel = (lang) => {
    if (customLabels && customLabels[lang.code]) {
      return customLabels[lang.code];
    }
    return showLanguageCode ? `${lang.name} (${lang.code})` : lang.name;
  };

  const target = openInNewWindow ? ' target="_blank"' : "";
  const rel = openInNewWindow ? ' rel="noopener noreferrer"' : "";
  const strings = (typeof wpccFrontendSettings !== 'undefined' && wpccFrontendSettings.strings) ? wpccFrontendSettings.strings : {};
  const noConversionText = customNoConversionLabel || strings.noConversionLabel || "不转换";

  let html = "";

  if (displayStyle === "horizontal") {
    html = '<div class="wpcc-horizontal-switcher">';

    if (showNoConversion) {
      const isActive = !currentLang || currentLang === "";
      const noConvUrl = getNoConversionUrl();
      const relForNoConv = buildRelAttribute(noConvUrl, openInNewWindow);
      html += `<span class="wpcc-lang-item wpcc-no-conversion ${isActive ? "wpcc-current" : ""}">
                <a href="${noConvUrl}" class="wpcc-link"${target}${relForNoConv}>${noConversionText}</a>
            </span>`;
    }

    availableLanguages.forEach(function (lang) {
      const isActive = currentLang === lang.code;
      const label = getLanguageLabel(lang);
      const ariaCurrent = isActive ? ' aria-current="page"' : '';
      html += `<span class="wpcc-lang-item ${isActive ? "wpcc-current" : ""}">
                <a href="${getLanguageUrl(lang.code)}" class="wpcc-link" title="${lang.label}"${target}${rel}${ariaCurrent}>${label}</a>
            </span>`;
    });

    html += "</div>";
  } else {
    const ariaLabel = (typeof strings !== 'undefined' && strings.languageSelectLabel) ? strings.languageSelectLabel : '选择语言';
    html =
      `<select class="wpcc-dropdown-switcher" aria-label="${ariaLabel}" onchange="handleLanguageChange(this.value, this)">`;

    if (showNoConversion) {
      const selected = !currentLang || currentLang === "" ? "selected" : "";
      html += `<option value="" ${selected}>${noConversionText}</option>`;
    }

    availableLanguages.forEach(function (lang) {
      const selected = currentLang === lang.code ? "selected" : "";
      const label = getLanguageLabel(lang);
      html += `<option value="${lang.code}" ${selected}>${label}</option>`;
    });

    html += "</select>";
  }

  placeholder.innerHTML = html;

  if (openInNewWindow) {
    placeholder.setAttribute("data-open-new-window", "true");
  }
}

function sortLanguages(languages, sortOrder) {
  let sorted = [...languages];

  switch (sortOrder) {
    case "alphabetical":
      sorted.sort((a, b) => a.name.localeCompare(b.name));
      break;
    case "frequency":
      const frequencyOrder = [
        "zh-cn",
        "zh-tw",
        "zh-hk",
        "zh-sg",
        "zh-hans",
        "zh-hant",
        "zh-jp",
      ];
      sorted.sort(
        (a, b) =>
          frequencyOrder.indexOf(a.code) - frequencyOrder.indexOf(b.code),
      );
      break;
    default:
      break;
  }

  return sorted;
}

function initConversionStatus() {
  const statusElements = document.querySelectorAll(".wpcc-conversion-status");

  statusElements.forEach(function (element) {
    const container = element.querySelector(".wpcc-status-container");
    if (container) {
      const showIcon = container.getAttribute("data-show-icon") === "true";
      const displayFormat = container.getAttribute("data-display-format");

      renderConversionStatus(container, showIcon, displayFormat);
    }
  });
}

function renderConversionStatus(container, showIcon, displayFormat) {
  const currentLang = getCurrentLanguage();
  const langInfo = getLanguageInfo(currentLang);

  let html = "";

  const strings = (typeof wpccFrontendSettings !== 'undefined' && wpccFrontendSettings.strings) ? wpccFrontendSettings.strings : {};
  const currentPrefix = strings.currentLanguagePrefix || '当前语言：';

  let statusText = "";
  switch (displayFormat) {
    case "badge":
      statusText = langInfo.label;
      break;
    case "text":
      statusText = `${currentPrefix}${langInfo.label}`;
      break;
    case "minimal":
      statusText = langInfo.name;
      break;
    default:
      statusText = langInfo.label;
  }

  if (showIcon) {
    html += `<span class="dashicons dashicons-translation" aria-hidden="true"></span>`;
  }
  html += `<span class="wpcc-status-text">${statusText}</span>`;

  container.innerHTML = html;
}

function initNoConversionBlocks() {
  const noConversionBlocks = document.querySelectorAll(
    ".wpcc-no-conversion-wrapper",
  );

  noConversionBlocks.forEach(function (block) {
    block.setAttribute("data-wpcc-no-conversion", "true");

    const content = block.querySelector(".wpcc-no-conversion-content");
    if (content) {
      content.setAttribute("data-wpcc-protected", "true");
    }


  });
}

function getAvailableLanguages() {
  const allLanguages = [
    { code: "zh-cn", name: "简体", label: "简体中文" },
    { code: "zh-tw", name: "繁体", label: "台湾正体" },
    { code: "zh-hk", name: "港澳", label: "港澳繁体" },
    { code: "zh-sg", name: "马新", label: "马新简体" },
    { code: "zh-hans", name: "简体", label: "简体中文" },
    { code: "zh-hant", name: "繁体", label: "繁体中文" },
    { code: "zh-jp", name: "日式", label: "日式汉字" },
  ];
  
  if (typeof wpccFrontendSettings !== 'undefined' && wpccFrontendSettings.enabledLanguages) {
    const enabledCodes = wpccFrontendSettings.enabledLanguages;
    const enabledLanguages = allLanguages.filter(lang => enabledCodes.includes(lang.code));
    
    if (wpccFrontendSettings.languageLabels) {
      enabledLanguages.forEach(lang => {
        if (wpccFrontendSettings.languageLabels[lang.code]) {
          lang.name = wpccFrontendSettings.languageLabels[lang.code];
        }
      });
    }
    
    return enabledLanguages;
  }
  
  return allLanguages;
}

function getCurrentLanguage() {
  // 优先使用服务端注入的当前语言（如果存在）
  if (typeof wpcc_target_lang !== "undefined" && wpcc_target_lang) {
    return wpcc_target_lang;
  }

  const urlParams = new URLSearchParams(window.location.search);
  const variant = urlParams.get("variant");

  if (variant) {
    return variant;
  }

  const pathMatch = window.location.pathname.match(/\/(zh-[a-z]+)\//);
  if (pathMatch) {
    return pathMatch[1];
  }

  return "";
}

function getLanguageInfo(langCode) {
  const languages = getAvailableLanguages();
  const lang = languages.find((l) => l.code === langCode);

  if (lang) {
    return lang;
  }

  return { code: "", name: "不转换", label: "不转换" };
}

function getLanguageUrl(langCode) {
  // 优先使用服务端注入的 URL 映射（如可用）
  try {
    if (typeof wpcc_langs_urls === "object" && wpcc_langs_urls) {
      if (langCode && wpcc_langs_urls[langCode]) {
        return wpcc_langs_urls[langCode];
      }
      if (!langCode || langCode === "") {
        if (typeof wpcc_noconversion_url === "string" && wpcc_noconversion_url) {
          return wpcc_noconversion_url;
        }
      }
    }
  } catch (e) {
    // 忽略映射读取错误，回退到查询参数模式
  }

  // 回退：使用查询参数模式（并避免重复：/zh-xx/ 与 ?variant=zh-xx 共存）
  const currentUrl = new URL(window.location.href);
  const pathMatch = currentUrl.pathname.match(/^\/(zh-[a-z]+)(\b|\/)/i);

  if (langCode) {
    // 若当前已处于同一变体路径，则仅移除冗余的 variant 参数，返回干净的漂亮链接
    if (pathMatch && pathMatch[1].toLowerCase() === langCode.toLowerCase()) {
      currentUrl.searchParams.delete("variant");
      return currentUrl.toString();
    }
    currentUrl.searchParams.set("variant", langCode);
    return currentUrl.toString();
  } else {
    // 不转换：尽量去除语言段与冗余参数
    currentUrl.searchParams.delete("variant");
    if (pathMatch) {
      // 去掉开头的 /zh-xx 段，回到原始路径
      currentUrl.pathname = currentUrl.pathname.replace(/^\/(zh-[a-z]+)(\/?)/i, "/");
    }
    return currentUrl.toString();
  }
}

// 构建“不转换”链接：在变体页面时注入 zh 哨兵以覆盖浏览器/Cookie 策略
function getNoConversionUrl() {
  // 基于服务端注入的原始 URL 获取基础地址
  let baseUrl = (typeof wpcc_noconversion_url === "string" && wpcc_noconversion_url)
    ? wpcc_noconversion_url
    : (function() {
        const u = new URL(window.location.href);
        // 去除 variant 查询与路径前缀
        u.searchParams.delete("variant");
        u.pathname = u.pathname.replace(/^\/(zh-[a-z]+)(\/?)/i, "/");
        return u.toString();
      })();

  const currentLang = getCurrentLanguage();
  if (!currentLang) {
    return baseUrl; // 已经是不转换
  }

  // 检测站点使用的链接风格：查询参数 / 后缀 / 前缀
  const detectStyle = () => {
    try {
      if (typeof wpcc_langs_urls === 'object' && wpcc_langs_urls) {
        for (const k in wpcc_langs_urls) {
          if (!Object.prototype.hasOwnProperty.call(wpcc_langs_urls, k)) continue;
          const href = String(wpcc_langs_urls[k] || '');
          if (/([?&])variant=zh-[a-z]+/i.test(href)) return 'query';
          const u = new URL(href, window.location.origin);
          const path = u.pathname;
          // 前缀: /zh-xx/...  后缀: .../zh-xx/
          if (/^\/(zh-[a-z]+)(\/|$)/i.test(path)) return 'prefix';
          if (/(\/)(zh-[a-z]+)\/?$/i.test(path)) return 'suffix';
        }
      }
    } catch (e) {}
    return 'query';
  };

  const style = detectStyle();
  try {
    const u = new URL(baseUrl);
    if (style === 'query') {
      u.searchParams.set('variant', 'zh');
      return u.toString();
    }
    if (style === 'suffix') {
      // 确保末尾带 /zh/
      u.pathname = u.pathname.replace(/\/$/, '') + '/zh/';
      return u.toString();
    }
    // prefix
    // 将路径改为 /zh/ + 原路径
    u.pathname = u.pathname.replace(/^\/+/, '/');
    u.pathname = '/zh' + (u.pathname.startsWith('/') ? '' : '/') + u.pathname;
    // 合并多余斜杠
    u.pathname = u.pathname.replace(/\/{2,}/g, '/');
    return u.toString();
  } catch (e) {
    // 回退：总是可用的查询参数
    try {
      const u2 = new URL(baseUrl);
      u2.searchParams.set('variant', 'zh');
      return u2.toString();
    } catch (_e) {
      return baseUrl;
    }
  }
}

// 根据链接是否包含 zh 哨兵决定 rel，且兼容新窗口的 noopener noreferrer
function buildRelAttribute(url, openInNewWindow) {
  const hasZhSentinel = /\/(?:zh)(?:\b|\/)/i.test(url) || /(?:[?&])variant=zh(?:&|$)/i.test(url);
  let relParts = [];
  if (hasZhSentinel) relParts.push('nofollow');
  if (openInNewWindow) relParts.push('noopener', 'noreferrer');
  return relParts.length ? ` rel="${Array.from(new Set(relParts)).join(' ')}"` : '';
}

function handleLanguageChange(langCode, selectElement) {
  const url = getLanguageUrl(langCode);
  const openInNewWindow =
    selectElement &&
    selectElement
      .closest(".wpcc-language-switcher")
      .getAttribute("data-open-new-window") === "true";

  if (openInNewWindow) {
    window.open(url, "_blank");
  } else {
    window.location.href = url;
  }
}
