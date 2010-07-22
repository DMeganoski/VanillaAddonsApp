jQuery(document).ready(function($) {
   setTimeout(function() {
      var loc = location.href;
      if (loc.substring(0,-1) != '/')
         loc += '/';

      loc += '1';
      document.location = loc;
   }, 3000);
});