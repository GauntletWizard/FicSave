// Sandstorm - Personal Cloud Sandbox
// Copyright (c) 2014 Sandstorm Development Group, Inc. and contributors
//

// Hack around stdlib bug with C++14.
#include <initializer_list> // force libstdc++ to include its config
#undef _GLIBCXX_HAVE_GETS // correct broken config
// End hack.

#include <kj/main.h>
#include <kj/debug.h>
#include <kj/async-io.h>
#include <kj/async-unix.h>
#include <kj/io.h>
#include <capnp/rpc-twoparty.h>
#include <capnp/rpc.capnp.h>
#include <capnp/ez-rpc.h>
#include <sandstorm/sandstorm-http-bridge.capnp.h>
#include <unistd.h>

#include <sandstorm/hack-session.capnp.h>

namespace sandstorm {

  class GetPublicIdMain {
  public:
    GetPublicIdMain(kj::ProcessContext& context): context(context) { }

    kj::MainFunc getMain() {
       return kj::MainBuilder(context, "GetPublicId version: 0.0.2",
                             "Runs the getPublicId command from hack-session.capnp. "
                             "Outputs the return arguments as separate lines on stdout.")
        .expectArg("<sessionId>", KJ_BIND_METHOD(*this, setSessionId))
        .expectArg("<url>", KJ_BIND_METHOD(*this, setUrl))
        .callAfterParsing(KJ_BIND_METHOD(*this, run))
        .build();
    }

    kj::MainBuilder::Validity setSessionId(kj::StringPtr id) {
      sessionId = kj::heapString(id);
      return true;
    }

    kj::MainBuilder::Validity setUrl(kj::StringPtr id) {
      url = kj::heapString(id);
      return true;
    }

    kj::MainBuilder::Validity run() {
      capnp::EzRpcClient client("unix:/tmp/sandstorm-api");
      SandstormHttpBridge::Client restorer = client.getMain<SandstormHttpBridge>();

      auto request = restorer.getSessionContextRequest();
      request.setId(sessionId);
      auto session = request.send().getContext().castAs<HackSessionContext>();

      auto httprequest = session.httpGetRequest();
			httprequest.setUrl(url);
			auto promise = httprequest.send().then([](auto result) {
          kj::FdOutputStream(STDOUT_FILENO).write(result.getContent().begin(), result.getContent().size());
        }, [] (auto e) {
          auto desc = e.getDescription();
          kj::FdOutputStream(STDOUT_FILENO).write(desc.begin(), desc.size());
        });

      promise.wait(client.getWaitScope());
      return true;
    }

  private:
    kj::ProcessContext& context;
    kj::String sessionId;
    kj::String url;
  };

} // namespace sandstorm

KJ_MAIN(sandstorm::GetPublicIdMain)

