define(['handlebars'], function(Handlebars) {

return Handlebars.template(function (Handlebars,depth0,helpers,partials,data) {
  this.compilerInfo = [2,'>= 1.0.0-rc.3'];
helpers = helpers || Handlebars.helpers; data = data || {};
  var buffer = "", stack1, functionType="function", escapeExpression=this.escapeExpression;


  buffer += "<div class=\"customattributes\"></div>\n\n<section class=\"keymedia-crop\">\n    <ul class=\"nav nav-pills inverted\"></ul>\n</section>\n\n<div class=\"keymedia-crop-container\">\n    <div class=\"image-wrap\">\n        <img src=\"";
  if (stack1 = helpers.media) { stack1 = stack1.call(depth0, {hash:{},data:data}); }
  else { stack1 = depth0.media; stack1 = typeof stack1 === functionType ? stack1.apply(depth0) : stack1; }
  buffer += escapeExpression(stack1)
    + "\" />\n    </div>\n</div>\n";
  return buffer;
  })

});