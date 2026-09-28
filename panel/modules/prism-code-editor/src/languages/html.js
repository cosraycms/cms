import { languageMap } from "../core.js";
import { markupComment, markupLanguage, voidTags, xmlOpeningTag } from "./shared/index.js";
languageMap.markup =
    languageMap.html =
        languageMap.markdown =
            languageMap.md =
                markupLanguage(markupComment, xmlOpeningTag, voidTags);
