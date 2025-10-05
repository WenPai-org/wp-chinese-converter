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
      html += `<span class="wpcc-lang-item wpcc-no-conversion ${isActive ? "wpcc-current" : ""}">
                <a href="${getLanguageUrl("")}" class="wpcc-link"${target}${rel}>${noConversionText}</a>
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
      if (!langCode || langCode === "") {
        if (typeof wpcc_noconversion_url === "string" && wpcc_noconversion_url) {
          return wpcc_noconversion_url;
        }
      } else if (wpcc_langs_urls[langCode]) {
        return wpcc_langs_urls[langCode];
      }
    }
  } catch (e) {
    // 忽略映射读取错误，回退到查询参数模式
  }

  // 回退：使用查询参数模式
  const currentUrl = new URL(window.location.href);
  if (langCode) {
    currentUrl.searchParams.set("variant", langCode);
  } else {
    currentUrl.searchParams.delete("variant");
  }

  return currentUrl.toString();
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
