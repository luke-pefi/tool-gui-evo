/**
 *
 * util.js - this is just a collection of helper function that we may or may not
 * need or fine useful as we code up the new Avaya theme.
 *
 * Tehse functions are lifted from:
 *
 *   http://locutus.io/php
 *
 * unless otherwise specified.
 *
 */

function in_array (needle, haystack, argStrict) { // eslint-disable-line camelcase

  //  discuss at: http://locutus.io/php/in_array/
  // original by: Kevin van Zonneveld (http://kvz.io)
  // improved by: vlado houba
  // improved by: Jonas Sciangula Street (Joni2Back)
  //    input by: Billy
  // bugfixed by: Brett Zamir (http://brett-zamir.me)
  //   example 1: in_array('van', ['Kevin', 'van', 'Zonneveld'])
  //   returns 1: true
  //   example 2: in_array('vlado', {0: 'Kevin', vlado: 'van', 1: 'Zonneveld'})
  //   returns 2: false
  //   example 3: in_array(1, ['1', '2', '3'])
  //   example 3: in_array(1, ['1', '2', '3'], false)
  //   returns 3: true
  //   returns 3: true
  //   example 4: in_array(1, ['1', '2', '3'], true)
  //   returns 4: false

  var key = ''
  var strict = !!argStrict

  // we prevent the double check (strict && arr[key] === ndl) || (!strict && arr[key] === ndl)
  // in just one for, in order to improve the performance
  // deciding wich type of comparation will do before walk array
  if (strict) {
    for (key in haystack) {
      if (haystack[key] === needle) {
        return true
      }
    }
  } else {
    for (key in haystack) {
      if (haystack[key] == needle) { // eslint-disable-line eqeqeq
        return true
      }
    }
  }

  return false
}

function echo () {

  //  discuss at: http://locutus.io/php/echo/
  // original by: Philip Peterson
  // improved by: echo is bad
  // improved by: Nate
  // improved by: Brett Zamir (http://brett-zamir.me)
  // improved by: Brett Zamir (http://brett-zamir.me)
  // improved by: Brett Zamir (http://brett-zamir.me)
  //  revised by: Der Simon (http://innerdom.sourceforge.net/)
  // bugfixed by: Eugene Bulkin (http://doubleaw.com/)
  // bugfixed by: Brett Zamir (http://brett-zamir.me)
  // bugfixed by: Brett Zamir (http://brett-zamir.me)
  // bugfixed by: EdorFaus
  //      note 1: In 1.3.2 and earlier, this function wrote to the body of the document when it
  //      note 1: was called in webbrowsers, in addition to supporting XUL.
  //      note 1: This involved >100 lines of boilerplate to do this in a safe way.
  //      note 1: Since I can't imageine a complelling use-case for this, and XUL is deprecated
  //      note 1: I have removed this behavior in favor of just calling `console.log`
  //      note 2: You'll see functions depends on `echo` instead of `console.log` as we'll want
  //      note 2: to have 1 contact point to interface with the outside world, so that it's easy
  //      note 2: to support other ways of printing output.
  //  revised by: Kevin van Zonneveld (http://kvz.io)
  //    input by: JB
  //   example 1: echo('Hello world')
  //   returns 1: undefined

  var args = Array.prototype.slice.call(arguments)
  return console.log(args.join(' '))
}

function is_array (mixedVar) { // eslint-disable-line camelcase

  //  discuss at: http://locutus.io/php/is_array/
  // original by: Kevin van Zonneveld (http://kvz.io)
  // improved by: Legaev Andrey
  // improved by: Onno Marsman (https://twitter.com/onnomarsman)
  // improved by: Brett Zamir (http://brett-zamir.me)
  // improved by: Nathan Sepulveda
  // improved by: Brett Zamir (http://brett-zamir.me)
  // bugfixed by: Cord
  // bugfixed by: Manish
  // bugfixed by: Brett Zamir (http://brett-zamir.me)
  //      note 1: In Locutus, javascript objects are like php associative arrays,
  //      note 1: thus JavaScript objects will also
  //      note 1: return true in this function (except for objects which inherit properties,
  //      note 1: being thus used as objects),
  //      note 1: unless you do ini_set('locutus.objectsAsArrays', 0),
  //      note 1: in which case only genuine JavaScript arrays
  //      note 1: will return true
  //   example 1: is_array(['Kevin', 'van', 'Zonneveld'])
  //   returns 1: true
  //   example 2: is_array('Kevin van Zonneveld')
  //   returns 2: false
  //   example 3: is_array({0: 'Kevin', 1: 'van', 2: 'Zonneveld'})
  //   returns 3: true
  //   example 4: ini_set('locutus.objectsAsArrays', 0)
  //   example 4: is_array({0: 'Kevin', 1: 'van', 2: 'Zonneveld'})
  //   returns 4: false
  //   example 5: is_array(function tmp_a (){ this.name = 'Kevin' })
  //   returns 5: false

  var _getFuncName = function (fn) {

    var name = (/\W*function\s+([\w\$]+)\s*\(/).exec(fn)

    if (!name) {
      return '(Anonymous)'
    }
    return name[1]
  }
  var _isArray = function (mixedVar) {

    // return Object.prototype.toString.call(mixedVar) === '[object Array]';
    // The above works, but let's do the even more stringent approach:
    // (since Object.prototype.toString could be overridden)
    // Null, Not an object, no length property so couldn't be an Array (or String)
    if (!mixedVar || typeof mixedVar !== 'object' || typeof mixedVar.length !== 'number') {
      return false
    }
    var len = mixedVar.length
    mixedVar[mixedVar.length] = 'bogus'

    // The only way I can think of to get around this (or where there would be trouble)
    // would be to have an object defined
    // with a custom "length" getter which changed behavior on each call
    // (or a setter to mess up the following below) or a custom
    // setter for numeric properties, but even that would need to listen for
    // specific indexes; but there should be no false negatives
    // and such a false positive would need to rely on later JavaScript
    // innovations like __defineSetter__

    if (len !== mixedVar.length) {
      // We know it's an array since length auto-changed with the addition of a
      // numeric property at its length end, so safely get rid of our bogus element
      mixedVar.length -= 1
      return true
    }

    // Get rid of the property we added onto a non-array object; only possible
    // side-effect is if the user adds back the property later, it will iterate
    // this property in the older order placement in IE (an order which should not
    // be depended on anyways)
    delete mixedVar[mixedVar.length]
    return false
  }

  if (!mixedVar || typeof mixedVar !== 'object') {
    return false
  }

  var isArray = _isArray(mixedVar)

  if (isArray) {
    return true
  }

  var iniVal = (typeof require !== 'undefined' ? require('../info/ini_get')('locutus.objectsAsArrays') : undefined) || 'on'

  if (iniVal === 'on') {

    var asString = Object.prototype.toString.call(mixedVar)
    var asFunc = _getFuncName(mixedVar.constructor)

    if (asString === '[object Object]' && asFunc === 'Object') {
      // Most likely a literal and intended as assoc. array
      return true
    }
  }

  return false
}

function is_bool (mixedVar) { // eslint-disable-line camelcase

  //  discuss at: http://locutus.io/php/is_bool/
  // original by: Onno Marsman (https://twitter.com/onnomarsman)
  // improved by: CoursesWeb (http://www.coursesweb.net/)
  //   example 1: is_bool(false)
  //   returns 1: true
  //   example 2: is_bool(0)
  //   returns 2: false

  return (mixedVar === true || mixedVar === false) // Faster (in FF) than type checking
}

function is_numeric (mixedVar) { // eslint-disable-line camelcase

  //  discuss at: http://locutus.io/php/is_numeric/
  // original by: Kevin van Zonneveld (http://kvz.io)
  // improved by: David
  // improved by: taith
  // bugfixed by: Tim de Koning
  // bugfixed by: WebDevHobo (http://webdevhobo.blogspot.com/)
  // bugfixed by: Brett Zamir (http://brett-zamir.me)
  // bugfixed by: Denis Chenu (http://shnoulle.net)
  //   example 1: is_numeric(186.31)
  //   returns 1: true
  //   example 2: is_numeric('Kevin van Zonneveld')
  //   returns 2: false
  //   example 3: is_numeric(' +186.31e2')
  //   returns 3: true
  //   example 4: is_numeric('')
  //   returns 4: false
  //   example 5: is_numeric([])
  //   returns 5: false
  //   example 6: is_numeric('1 ')
  //   returns 6: false

  var whitespace = [
    ' ',
    '\n',
    '\r',
    '\t',
    '\f',
    '\x0b',
    '\xa0',
    '\u2000',
    '\u2001',
    '\u2002',
    '\u2003',
    '\u2004',
    '\u2005',
    '\u2006',
    '\u2007',
    '\u2008',
    '\u2009',
    '\u200a',
    '\u200b',
    '\u2028',
    '\u2029',
    '\u3000'
  ].join('')

  // @todo: Break this up using many single conditions with early returns

  return (typeof mixedVar === 'number' || (typeof mixedVar === 'string' && whitespace.indexOf(mixedVar.slice(-1)) === -1)) &&
    mixedVar !== '' &&
    !isNaN(mixedVar)
}

function is_object (mixedVar) { // eslint-disable-line camelcase

  //  discuss at: http://locutus.io/php/is_object/
  // original by: Kevin van Zonneveld (http://kvz.io)
  // improved by: Legaev Andrey
  // improved by: Michael White (http://getsprink.com)
  //   example 1: is_object('23')
  //   returns 1: false
  //   example 2: is_object({foo: 'bar'})
  //   returns 2: true
  //   example 3: is_object(null)
  //   returns 3: false

  if (Object.prototype.toString.call(mixedVar) === '[object Array]') {
    return false
  }
  return mixedVar !== null && typeof mixedVar === 'object'
}

function is_string (mixedVar) { // eslint-disable-line camelcase

  //  discuss at: http://locutus.io/php/is_string/
  // original by: Kevin van Zonneveld (http://kvz.io)
  //   example 1: is_string('23')
  //   returns 1: true
  //   example 2: is_string(23.5)
  //   returns 2: false

  return (typeof mixedVar === 'string')
}

function isset () {

  //  discuss at: http://locutus.io/php/isset/
  // original by: Kevin van Zonneveld (http://kvz.io)
  // improved by: FremyCompany
  // improved by: Onno Marsman (https://twitter.com/onnomarsman)
  // improved by: RafaÅ‚ Kukawski (http://blog.kukawski.pl)
  //   example 1: isset( undefined, true)
  //   returns 1: false
  //   example 2: isset( 'Kevin van Zonneveld' )
  //   returns 2: true

  var a = arguments
  var l = a.length
  var i = 0
  var undef

  if (l === 0) {
    throw new Error('Empty isset')
  }

  while (i !== l) {
    if (a[i] === undef || a[i] === null) {
      return false
    }
    i++
  }

  return true
}

function empty(mixedVar) {

  //  discuss at: http://locutus.io/php/empty/
  // original by: Philippe Baumann
  //    input by: Onno Marsman (https://twitter.com/onnomarsman)
  //    input by: LH
  //    input by: Stoyan Kyosev (http://www.svest.org/)
  // bugfixed by: Kevin van Zonneveld (http://kvz.io)
  // improved by: Onno Marsman (https://twitter.com/onnomarsman)
  // improved by: Francesco
  // improved by: Marc Jansen
  // improved by: RafaÅ‚ Kukawski (http://blog.kukawski.pl)
  //   example 1: empty(null)
  //   returns 1: true
  //   example 2: empty(undefined)
  //   returns 2: true
  //   example 3: empty([])
  //   returns 3: true
  //   example 4: empty({})
  //   returns 4: true
  //   example 5: empty({'aFunc' : function () { alert('humpty'); } })
  //   returns 5: false

  var undef
  var key
  var i
  var len
  var emptyValues = [undef, null, false, 0, '', '0']

  for (i = 0, len = emptyValues.length; i < len; i++) {
    if (mixedVar === emptyValues[i]) {
      return true
    }
  }

  if (typeof mixedVar === 'object') {
    for (key in mixedVar) {
      if (mixedVar.hasOwnProperty(key)) {
        return false
      }
    }
    return true
  }

  return false
}

function strlen(string) {

  //  discuss at: http://locutus.io/php/strlen/
  // original by: Kevin van Zonneveld (http://kvz.io)
  // improved by: Sakimori
  // improved by: Kevin van Zonneveld (http://kvz.io)
  //    input by: Kirk Strobeck
  // bugfixed by: Onno Marsman (https://twitter.com/onnomarsman)
  //  revised by: Brett Zamir (http://brett-zamir.me)
  //      note 1: May look like overkill, but in order to be truly faithful to handling all Unicode
  //      note 1: characters and to this function in PHP which does not count the number of bytes
  //      note 1: but counts the number of characters, something like this is really necessary.
  //   example 1: strlen('Kevin van Zonneveld')
  //   returns 1: 19
  //   example 2: ini_set('unicode.semantics', 'on')
  //   example 2: strlen('A\ud87e\udc04Z')
  //   returns 2: 3

  var str = string + ''

  var iniVal = (typeof require !== 'undefined' ? require('../info/ini_get')('unicode.semantics') : undefined) || 'off'
  if (iniVal === 'off') {
    return str.length
  }

  var i = 0
  var lgth = 0

  var getWholeChar = function (str, i) {
    var code = str.charCodeAt(i)
    var next = ''
    var prev = ''
    if (code >= 0xD800 && code <= 0xDBFF) {
      // High surrogate (could change last hex to 0xDB7F to
      // treat high private surrogates as single characters)
      if (str.length <= (i + 1)) {
        throw new Error('High surrogate without following low surrogate')
      }
      next = str.charCodeAt(i + 1)
      if (next < 0xDC00 || next > 0xDFFF) {
        throw new Error('High surrogate without following low surrogate')
      }
      return str.charAt(i) + str.charAt(i + 1)
    } else if (code >= 0xDC00 && code <= 0xDFFF) {
      // Low surrogate
      if (i === 0) {
        throw new Error('Low surrogate without preceding high surrogate')
      }
      prev = str.charCodeAt(i - 1)
      if (prev < 0xD800 || prev > 0xDBFF) {
        // (could change last hex to 0xDB7F to treat high private surrogates
        // as single characters)
        throw new Error('Low surrogate without preceding high surrogate')
      }
      // We can pass over low surrogates now as the second
      // component in a pair which we have already processed
      return false
    }
    return str.charAt(i)
  }

  for (i = 0, lgth = 0; i < str.length; i++) {
    if ((getWholeChar(str, i)) === false) {
      continue
    }
    // Adapt this line at the top of any loop, passing in the whole string and
    // the current iteration and returning a variable to represent the individual character;
    // purpose is to treat the first part of a surrogate pair as the whole character and then
    // ignore the second part
    lgth++
  }

  return lgth
}

function is_callable(mixedVar, syntaxOnly, callableName) {

  //  discuss at: http://locutus.io/php/is_callable/
  // original by: Brett Zamir (http://brett-zamir.me)
  //    input by: FranÃ§ois
  // improved by: Brett Zamir (http://brett-zamir.me)
  //      note 1: The variable callableName cannot work as a string variable passed by
  //      note 1: reference as in PHP (since JavaScript does not support passing
  //      note 1: strings by reference), but instead will take the name of
  //      note 1: a global variable and set that instead.
  //      note 1: When used on an object, depends on a constructor property
  //      note 1: being kept on the object prototype
  //      note 2: Depending on the `callableName` that is passed, this function can use eval.
  //      note 2: The eval input is however checked to only allow valid function names,
  //      note 2: So it should not be unsafer than uses without eval (seeing as you can)
  //      note 2: already pass any function to be executed here.
  //   example 1: is_callable('is_callable')
  //   returns 1: true
  //   example 2: is_callable('bogusFunction', true)
  //   returns 2: true // gives true because does not do strict checking
  //   example 3: function SomeClass () {}
  //   example 3: SomeClass.prototype.someMethod = function (){}
  //   example 3: var testObj = new SomeClass()
  //   example 3: is_callable([testObj, 'someMethod'], true, 'myVar')
  //   example 3: var $result = myVar
  //   returns 3: 'SomeClass::someMethod'
  //   example 4: is_callable(function () {})
  //   returns 4: true

  var $global = (typeof window !== 'undefined' ? window : global)

  var validJSFunctionNamePattern = /^[_$a-zA-Z\xA0-\uFFFF][_$a-zA-Z0-9\xA0-\uFFFF]*$/

  var name = ''
  var obj = {}
  var method = ''
  var validFunctionName = false

  var getFuncName = function (fn) {
    var name = (/\W*function\s+([\w\$]+)\s*\(/).exec(fn)
    if (!name) {
      return '(Anonymous)'
    }
    return name[1]
  }

  if (typeof mixedVar === 'string') {
    obj = $global
    method = mixedVar
    name = mixedVar
    validFunctionName = !!name.match(validJSFunctionNamePattern)
  } else if (typeof mixedVar === 'function') {
    return true
  } else if (Object.prototype.toString.call(mixedVar) === '[object Array]' &&
    mixedVar.length === 2 &&
    typeof mixedVar[0] === 'object' &&
    typeof mixedVar[1] === 'string') {
    obj = mixedVar[0]
    method = mixedVar[1]
    name = (obj.constructor && getFuncName(obj.constructor)) + '::' + method
  } else {
    return false
  }

  if (syntaxOnly || typeof obj[method] === 'function') {
    if (callableName) {
      $global[callableName] = name
    }
    return true
  }

  // validFunctionName avoids exploits

  if (validFunctionName && typeof eval(method) === 'function') { // eslint-disable-line no-eval
    if (callableName) {
      $global[callableName] = name
    }
    return true
  }

  return false
}
