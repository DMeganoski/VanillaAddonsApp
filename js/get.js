jQuery(document).ready(function($) {
   setTimeout(function() {
      var loc = location.href;
      if (loc.substring(0,-4) != '.zip')
         loc += '.';

      loc += 'zip';
      document.location.replace(loc);
   }, 3000);
});