# Polylang Copy Content

**Contributors:** [Teemu Suoranta](https://github.com/TeemuSuoranta), [leemon](https://github.com/theleemon) (this plugin uses bits from  [Sync Attachments for Polylang](https://github.com/theleemon/sync-attachments-for-polylang))


**Tags:** polylang, media, attachments, admin, copy, content


**License:** GPLv2 or later

## Description

Polylang Copy Content is an add-on for the multilingual WordPress plugin [Polylang](https://wordpress.org/plugins/polylang/). This add-on let's you copy the content and the title WPML style when creating a new translation. All the images and galleries are translated automatically if you use media translations.


Basic feature list:

 * Copy title, content and attachments for new translation
 * Choose the language you want to copy from (make translation from the translated version's editor)
 * Get useful translation markup for captions and title like (es translation) to be overwritten
 * Media translation works for images, captions, galleries and featured image (if you use media translations)
 * Use various filters to modify copied content in code (to be documented and expanded)
 * Translations are done with Polylang's functions, no messing around

**The plugin is still in test phase and I'd like to get feedback and tackle a few issues before going to WordPress.org. Please, report issues and contribute!**


## Installation

Download and activate. That's it. You will need Polylang, too (d'oh).

**Composer:**
```
$ composer aucor/polylang-copy-content
```
**With composer.json:**
```
{
  "require": {
    "aucor/polylang-copy-content": "*"
  },
  "extra": {
    "installer-paths": {
      "htdocs/wp-content/plugins/{$name}/": ["type:wordpress-plugin"]
    }
  }
}
```

## Issues and feature whishlist

**Issues:**

 * Translating a link to a featured media page seems to make a broken link sometimes.
 * HTML is parsed with regex. [This might be a problem.](http://stackoverflow.com/a/1732454) ~~I will use PHP Simple HTML DOM Parser for HTML elements in the future and use regex for shortcodes~~. DOM Parsers bring new problems, let's not.
 * Translating featured image is somehow broken. The image can be translated and placed but if you'll try to edit the captions before the translation has been saved the first time the correct image is not selected in the media gallery. After you have saved it will work as it should.
 * Adding translation markup (fr translation) might be someting everybody won't like. Maybe I should ~~drop this in future or~~ make it optional.

 
 **Feature whishlist:**

 * UI to handel wheteher you want to copy things and choose the language
 * Making the plugin itself translatable
 * Ability to copy images to translation after translation has been created (if new images are added to the original version)
 * Undo copying (remove image translations)
 * ~~:star::star: Get integrated to Polylang :star::star:~~ Polylang Pro has similar function that was build unknowingly of this plugin.
