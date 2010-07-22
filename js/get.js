jQuery(document).ready(function($) {
   /*
     Used for prompting download of addons & other files.
     Redirects to the current url with an additional /1/ parameter
   */
   var loc = location.href;
   if (loc.substring(0,-1) != '/')
      loc += '/';
   
   loc += '1';
   document.location = loc;
});