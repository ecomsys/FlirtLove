// public/js/tinymce.config.js
window.getTinyMceConfig = function(isDark, textColor, bgColor, borderColor, mutedColor, mutedBgColor) {
    return {
        // Основные настройки
        license_key: 'gpl',
        menubar: false,
        height: '100%',
        icons: 'default',
        plugins: 'lists link table image autolink wordcount code fullscreen quickbars',
        toolbar: 'undo redo | blocks | bold italic underline strikethrough | alignleft aligncenter alignright | bullist numlist outdent indent | link table image | code fullscreen',
        skin: isDark ? 'oxide-dark' : 'oxide',
        content_css: isDark ? 'dark' : 'default',
        statusbar: false, 
        placeholder: '',
        
        // Стили контента редактора
        content_style: `
            body { 
                background-color: ${bgColor} !important; 
                color: ${textColor} !important;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; 
                font-size: 14px; 
                line-height: 1.6; 
                padding: 16px; 
                margin: 0 !important;
            }
            h1, h2, h3, h4 { color: ${textColor} !important; }
            h1 { font-size: 24px; font-weight: 700; margin-top: 16px; margin-bottom: 8px; }
            h2 { font-size: 20px; font-weight: 600; margin-top: 16px; margin-bottom: 8px; }
            p { margin: 0 0 1rem 0; }
            a { color: #3b82f6; text-decoration: underline; }
            blockquote { border-left: 4px solid ${borderColor}; padding-left: 16px; color: ${mutedColor}; font-style: italic; margin: 1rem 0; }
            pre { background-color: ${mutedBgColor}; color: ${textColor}; padding: 1rem; border-radius: 6px; font-family: monospace; overflow-x: auto; }
            table { border-collapse: collapse; width: 100%; }
            th, td { border: 1px solid ${borderColor}; padding: 8px; }
        `
    };
};