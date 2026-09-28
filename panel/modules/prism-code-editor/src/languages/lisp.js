import { languageMap } from "../index.js";
import { bracketIndenting } from "./shared/index.js";
languageMap["emacs-lisp"] =
    languageMap.emacs =
        languageMap.elisp =
            languageMap.lisp =
                bracketIndenting({ line: ";" });
