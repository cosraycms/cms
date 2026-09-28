import { languageMap } from "../core.js";
import { markupLanguage } from "./shared/index.js";
// Same as HTML, but without void tags
languageMap.xml =
    languageMap.ssml =
        languageMap.atom =
            languageMap.rss =
                languageMap.mathml =
                    languageMap.svg =
                        markupLanguage();
