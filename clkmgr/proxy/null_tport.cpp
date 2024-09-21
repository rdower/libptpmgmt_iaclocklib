/* SPDX-License-Identifier: BSD-3-Clause
   SPDX-FileCopyrightText: Copyright © 2024 Intel Corporation. */

/** @file
 * @brief Null transport implementation. Non-functional for testing only.
 *
 * @author Christopher Hall <christopher.s.hall@intel.com>
 * @copyright © 2024 Intel Corporation.
 *
 */

#include "proxy/null_tport.hpp"
#include "common/print.hpp"

#include <cstdint>

__CLKMGR_NAMESPACE_USE

using namespace std;

SEND_PROXY_MESSAGE(NullProxyTransport::sendMessage)
{
    PrintError("NullTransport should not be used");
    return false;
}
