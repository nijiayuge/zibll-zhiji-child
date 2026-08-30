$('head').before('<div class="container1"><div class="inner-container1"><div class="shape"></div></div><div class="inner-container1"><div class="shape"></div></div></div>');
$(document).ready(function(){var html='';for(var i=1;i<=50;i++){html+='<div class="shape-container--'+i+' shape-animation"><div class="random-shape"></div></div>'}document.querySelector('.shape').innerHTML+=html});
