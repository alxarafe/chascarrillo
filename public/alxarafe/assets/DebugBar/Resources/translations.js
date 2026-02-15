if (typeof (PhpDebugBar) == 'undefined') {
    // namespace
    var PhpDebugBar = {};
    PhpDebugBar.$ = jQuery;
}

(function ($) {
    var csscls = PhpDebugBar.utils.makecsscls("phpdebugbar-widgets-");

    /**
     * Widget for the displaying translations data
     *
     * Options:
     *  - data
     */
    var TranslationsWidget = PhpDebugBar.Widgets.TranslationsWidget = PhpDebugBar.Widget.extend({
        className: csscls('translations'),
        render: function () {
            this.$status = $('<div />').addClass(csscls('status')).appendTo(this.$el);

            this.$list = new PhpDebugBar.Widgets.ListWidget({
                itemRenderer: function (li, translation) {
                    var text = translation.key + ": " + translation.value;
                    if (translation.key == translation.value) {
                        var $line = $('<span/>').addClass(csscls('name')).addClass('text-danger').text(text);
                    } else {
                        var $line = $('<span/>').addClass(csscls('name')).addClass('text-muted').text(text);
                    }

                    $line.appendTo(li);
                }
            });
            this.$list.$el.appendTo(this.$el);

            this.bindAttr('data', function (data) {
                this.$list.set('data', data.translations);
                if (data.translations) {
                    var sentence = data.sentence || "translations were missed for your language";
                    this.$status.empty().append($('<span />').text(data.translations.length + " " + sentence));
                }
            });
        }
    });

})(PhpDebugBar.$);